import hashlib
import math
import re
from abc import ABC, abstractmethod
from typing import Optional
import httpx

from app.config import settings
from app.schemas import ChatMessage, LLMConfig, SourceCitation


EMBEDDING_DIM = 128


def compute_deterministic_embedding(text: str, dim: int = EMBEDDING_DIM) -> list[float]:
    """
    Computes a language-agnostic (RU/EN) n-gram + token feature embedding vector
    normalized to unit length. Used for fast local/CI execution and hybrid retrieval
    without requiring paid external embedding calls.
    """
    vec = [0.0] * dim
    normalized = text.lower().strip()
    tokens = re.findall(r"[\wа-яё]{2,}", normalized)

    # Word tokens + character 3-grams for morphological resilience (Russian & English)
    features: list[tuple[str, float]] = [(tok, 2.0) for tok in tokens]
    for tok in tokens:
        if len(tok) >= 4:
            for i in range(len(tok) - 2):
                features.append((tok[i : i + 3], 0.8))

    if not features:
        features = [(normalized[:10] or "empty", 1.0)]

    for feat, weight in features:
        digest = hashlib.sha256(feat.encode("utf-8")).digest()
        idx = int.from_bytes(digest[:2], "big") % dim
        sign = 1.0 if (digest[2] % 2 == 0) else -1.0
        vec[idx] += sign * weight

    norm = math.sqrt(sum(v * v for v in vec)) or 1.0
    return [round(v / norm, 6) for v in vec]


def cosine_similarity(vec_a: list[float], vec_b: list[float]) -> float:
    if not vec_a or not vec_b or len(vec_a) != len(vec_b):
        return 0.0
    dot = sum(a * b for a, b in zip(vec_a, vec_b))
    norm_a = math.sqrt(sum(a * a for a in vec_a)) or 1.0
    norm_b = math.sqrt(sum(b * b for b in vec_b)) or 1.0
    return max(0.0, min(1.0, dot / (norm_a * norm_b)))


class BaseLLMProvider(ABC):
    provider_name: str
    default_model: str

    def __init__(self, api_key: Optional[str] = None, model: Optional[str] = None, base_url: Optional[str] = None):
        self.api_key = (api_key or "").strip()
        self.model = (model or "").strip() or self.default_model
        self.base_url = (base_url or "").strip()

    @abstractmethod
    async def generate_support_answer(
        self,
        query: str,
        sources: list[SourceCitation],
        history: list[ChatMessage],
        system_prompt: Optional[str] = None,
    ) -> tuple[str, str, str]:
        """Returns (answer_text, provider_used, model_used)."""
        raise NotImplementedError

    def _build_messages(
        self,
        query: str,
        sources: list[SourceCitation],
        history: list[ChatMessage],
        system_prompt: Optional[str] = None,
    ) -> tuple[str, list[dict[str, str]]]:
        context_block = "\n\n".join(
            f"[Source #{i + 1}: {s.title} ({s.category})]\n{s.excerpt}"
            for i, s in enumerate(sources)
        )
        base_system = system_prompt or (
            "You are PulseDesk AI, a helpful first-line customer support specialist for an online store. "
            "Answer the customer's question accurately and concisely using ONLY the provided knowledge base sources. "
            "Always reply in the same language as the customer's query (Russian or English). "
            "If the sources do not contain enough information, politely inform the customer that a human support agent will assist them shortly."
        )
        full_system = f"{base_system}\n\n=== KNOWLEDGE BASE CONTEXT ===\n{context_block or 'No matching documents found.'}"
        messages: list[dict[str, str]] = []
        for msg in history[-6:]:
            if msg.role in ("user", "assistant"):
                messages.append({"role": msg.role, "content": msg.content})
        messages.append({"role": "user", "content": query})
        return full_system, messages


class MockLLMProvider(BaseLLMProvider):
    """
    Deterministic grounded synthesizer used when no external API key is configured
    or during automated CI testing. Extracts the best matching knowledge base facts.
    """

    provider_name = "mock"
    default_model = "pulsedesk-rag-synthesizer-v1"

    async def generate_support_answer(
        self,
        query: str,
        sources: list[SourceCitation],
        history: list[ChatMessage],
        system_prompt: Optional[str] = None,
    ) -> tuple[str, str, str]:
        is_ru = bool(re.search(r"[а-яА-ЯёЁ]", query))
        if not sources:
            if is_ru:
                return (
                    "Я не нашёл точного ответа в базе знаний магазина по вашему вопросу. "
                    "Перевожу диалог на живого оператора поддержки — специалист ответит вам в ближайшее время.",
                    self.provider_name,
                    self.model,
                )
            return (
                "I could not find a definitive answer in our store knowledge base for your request. "
                "I am escalating this conversation to a human support specialist who will assist you shortly.",
                self.provider_name,
                self.model,
            )

        best = sources[0]
        if is_ru:
            answer = (
                f"По данным базы знаний («{best.title}»):\n\n"
                f"{best.excerpt}\n\n"
                f"Если вам нужна дополнительная помощь, нажмите кнопку «Позвать оператора»."
            )
        else:
            answer = (
                f"According to our knowledge base (**{best.title}**):\n\n"
                f"{best.excerpt}\n\n"
                f"If you need further assistance, feel free to click 'Call Human Agent'."
            )
        return answer, self.provider_name, self.model


class OpenAICompatibleProvider(BaseLLMProvider):
    """
    Shared implementation for OpenAI-compatible Chat Completions APIs:
    - OpenRouter (`https://openrouter.ai/api/v1`)
    - OpenCode (`https://api.opencode.ai/v1`)
    - OpenAI (`https://api.openai.com/v1`)
    """

    provider_name = "openrouter"
    default_model = "openai/gpt-4o-mini"
    default_base_url = "https://openrouter.ai/api/v1"

    async def generate_support_answer(
        self,
        query: str,
        sources: list[SourceCitation],
        history: list[ChatMessage],
        system_prompt: Optional[str] = None,
    ) -> tuple[str, str, str]:
        if not self.api_key:
            fallback = MockLLMProvider(model=f"{self.model} (local-fallback)")
            ans, _, _ = await fallback.generate_support_answer(query, sources, history, system_prompt)
            return ans, self.provider_name, f"{self.model} [fallback]"

        full_system, chat_messages = self._build_messages(query, sources, history, system_prompt)
        payload = {
            "model": self.model,
            "messages": [{"role": "system", "content": full_system}, *chat_messages],
            "temperature": 0.2,
            "max_tokens": 600,
        }
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }
        if self.provider_name == "openrouter":
            headers["HTTP-Referer"] = "https://pulsedesk.ai"
            headers["X-Title"] = "PulseDesk AI Support Deflector"

        endpoint = f"{(self.base_url or self.default_base_url).rstrip('/')}/chat/completions"
        try:
            async with httpx.AsyncClient(timeout=20.0) as client:
                resp = await client.post(endpoint, json=payload, headers=headers)
                resp.raise_for_status()
                data = resp.json()
                content = data["choices"][0]["message"]["content"].strip()
                return content, self.provider_name, self.model
        except Exception:
            fallback = MockLLMProvider(model=self.model)
            ans, _, _ = await fallback.generate_support_answer(query, sources, history, system_prompt)
            return ans, self.provider_name, f"{self.model} [fallback]"


class OpenRouterProvider(OpenAICompatibleProvider):
    provider_name = "openrouter"
    default_model = "openai/gpt-4o-mini"
    default_base_url = settings.openrouter_base_url


class OpenCodeProvider(OpenAICompatibleProvider):
    provider_name = "opencode"
    default_model = "opencode-chat-v1"
    default_base_url = settings.opencode_base_url


class OpenAIProvider(OpenAICompatibleProvider):
    provider_name = "openai"
    default_model = "gpt-4o-mini"
    default_base_url = settings.openai_base_url


class ClaudeProvider(BaseLLMProvider):
    """
    Native Anthropic Claude Messages API (`https://api.anthropic.com/v1/messages`).
    """

    provider_name = "claude"
    default_model = "claude-3-5-sonnet-latest"
    default_base_url = settings.claude_base_url

    async def generate_support_answer(
        self,
        query: str,
        sources: list[SourceCitation],
        history: list[ChatMessage],
        system_prompt: Optional[str] = None,
    ) -> tuple[str, str, str]:
        if not self.api_key:
            fallback = MockLLMProvider(model=f"{self.model} (local-fallback)")
            ans, _, _ = await fallback.generate_support_answer(query, sources, history, system_prompt)
            return ans, self.provider_name, f"{self.model} [fallback]"

        full_system, chat_messages = self._build_messages(query, sources, history, system_prompt)
        payload = {
            "model": self.model,
            "system": full_system,
            "messages": chat_messages,
            "max_tokens": 600,
            "temperature": 0.2,
        }
        headers = {
            "x-api-key": self.api_key,
            "anthropic-version": "2023-06-01",
            "Content-Type": "application/json",
        }
        endpoint = f"{(self.base_url or self.default_base_url).rstrip('/')}/messages"
        try:
            async with httpx.AsyncClient(timeout=20.0) as client:
                resp = await client.post(endpoint, json=payload, headers=headers)
                resp.raise_for_status()
                data = resp.json()
                blocks = data.get("content", [])
                text = "".join(b.get("text", "") for b in blocks if b.get("type") == "text").strip()
                return text, self.provider_name, self.model
        except Exception:
            fallback = MockLLMProvider(model=self.model)
            ans, _, _ = await fallback.generate_support_answer(query, sources, history, system_prompt)
            return ans, self.provider_name, f"{self.model} [fallback]"


class LLMRouterFactory:
    """
    Resolves the right LLM provider instance based on tenant configuration
    (openrouter, opencode, claude, openai, or mock) with environment key fallback.
    """

    SUPPORTED_PROVIDERS = ("openrouter", "opencode", "claude", "openai", "mock")

    @classmethod
    def create(cls, config: Optional[LLMConfig] = None) -> BaseLLMProvider:
        cfg = config or LLMConfig(
            provider=settings.default_llm_provider,  # type: ignore[arg-type]
            model=settings.default_llm_model,
        )
        provider = (cfg.provider or settings.default_llm_provider or "openrouter").lower()

        if provider == "openrouter":
            key = cfg.api_key or settings.openrouter_api_key
            return OpenRouterProvider(
                api_key=key,
                model=cfg.model or "openai/gpt-4o-mini",
                base_url=cfg.base_url or settings.openrouter_base_url,
            )
        if provider == "opencode":
            key = cfg.api_key or settings.opencode_api_key
            return OpenCodeProvider(
                api_key=key,
                model=cfg.model or "opencode-chat-v1",
                base_url=cfg.base_url or settings.opencode_base_url,
            )
        if provider == "claude":
            key = cfg.api_key or settings.claude_api_key
            return ClaudeProvider(
                api_key=key,
                model=cfg.model or "claude-3-5-sonnet-latest",
                base_url=cfg.base_url or settings.claude_base_url,
            )
        if provider == "openai":
            key = cfg.api_key or settings.openai_api_key
            return OpenAIProvider(
                api_key=key,
                model=cfg.model or "gpt-4o-mini",
                base_url=cfg.base_url or settings.openai_base_url,
            )

        return MockLLMProvider(model=cfg.model or "pulsedesk-rag-synthesizer-v1")
