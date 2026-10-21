import re
import time
from typing import Optional

from app.schemas import (
    CustomerIntent,
    DocumentIngestRequest,
    DocumentIngestResponse,
    ProviderVerifyRequest,
    ProviderVerifyResponse,
    RAGQueryRequest,
    RAGQueryResponse,
    SourceCitation,
)
from app.services.chunker import DocumentChunker
from app.services.llm_router import LLMRouterFactory
from app.services.vector_store import HybridVectorStore


class PromptInjectionGuardrail:
    """
    Production security guardrail that detects prompt injection, jailbreak,
    system prompt extraction, or unauthorized discount manipulation attempts.
    """

    INJECTION_PATTERNS = (
        r"ignore\s+(all\s+)?(previous|prior|above)\s+instructions",
        r"disregard\s+(all\s+)?(previous|prior|system)\s+instructions",
        r"reveal\s+(your\s+)?system\s+prompt",
        r"act\s+as\s+(dan|developer\s+mode|unrestricted)",
        r"забудь\s+(все\s+)?(предыдущие|прошлые|системные)\s+(инструкции|правила)",
        r"игнорируй\s+(все\s+)?(предыдущие|системные)\s+(инструкции|правила)",
        r"покажи\s+(свой\s+)?системный\s+промпт",
        r"(выдай|сделай|одобри)\s+скидку\s+(99|100)\s*%",
    )

    @classmethod
    def inspect(cls, query: str) -> tuple[bool, Optional[str]]:
        q = query.lower().strip()
        for pattern in cls.INJECTION_PATTERNS:
            if re.search(pattern, q):
                return True, "Security Guardrail: Prompt injection / policy manipulation attempt blocked."
        return False, None


class RAGEngine:
    """
    Core AI Support Deflector engine:
    1. Chunks and indexes merchant FAQ / Catalog / Return Policy docs
    2. Inspects incoming queries with PromptInjectionGuardrail
    3. Classifies incoming customer intent (RU & EN)
    4. Retrieves top-K chunks and calculates calibrated confidence score
    5. Decides whether to deflect automatically or escalate to a human operator
    6. Synthesizes grounded response via OpenRouter, OpenCode, Claude, or OpenAI
    """

    HUMAN_KEYWORDS = (
        r"\b(оператор\w*|человек\w*|менеджер\w*|живог\w+|поддержк\w+ живую|"
        r"human|operator|live agent|real person|speak to someone|manager)\b"
    )
    COMPLAINT_KEYWORDS = (
        r"\b(суд|полици\w*|мошенник\w*|обман\w*|ужасн\w+|жалоб\w+|прокуратур\w*|"
        r"scam|fraud|lawsuit|terrible|angry|unacceptable|stolen)\b"
    )
    RETURN_KEYWORDS = (
        r"\b(возврат\w*|вернуть|обмен\w*|деньги назад|гаранти\w*|брак\w*|"
        r"return|refund|exchange|warranty|defective|broken)\b"
    )
    ORDER_KEYWORDS = (
        r"\b(где мой заказ|статус заказ\w*|доставк\w*|трек|курьер\w*|когда приедет|"
        r"where is my order|order status|tracking|shipping|delivery)\b"
    )
    COMPAT_KEYWORDS = (
        r"\b(подойдет|совместим\w*|характеристик\w*|размер\w*|наличи\w*|цен\w*|стоимост\w*|"
        r"compatible|fit|specs|price|stock|available)\b"
    )

    def __init__(self, store: Optional[HybridVectorStore] = None):
        self.store = store or HybridVectorStore()

    @classmethod
    def classify_intent(cls, query: str) -> CustomerIntent:
        q = query.lower()
        if re.search(cls.HUMAN_KEYWORDS, q):
            return "human_request"
        if re.search(cls.COMPLAINT_KEYWORDS, q):
            return "complaint"
        if re.search(cls.RETURN_KEYWORDS, q):
            return "return_refund"
        if re.search(cls.ORDER_KEYWORDS, q):
            return "order_status"
        if re.search(cls.COMPAT_KEYWORDS, q):
            return "product_compatibility"
        return "faq_question"

    def ingest_document(self, req: DocumentIngestRequest) -> DocumentIngestResponse:
        chunks = DocumentChunker.split_text(
            req.content,
            chunk_size=req.chunk_size,
            chunk_overlap=req.chunk_overlap,
        )
        count = self.store.upsert_document_chunks(
            tenant_id=req.tenant_id,
            document_id=req.document_id,
            title=req.title,
            category=req.category,
            chunks=chunks,
        )
        return DocumentIngestResponse(
            status="indexed",
            tenant_id=req.tenant_id,
            document_id=req.document_id,
            title=req.title,
            chunks_count=count,
            vector_backend=self.store.backend_name,
        )

    def delete_document(self, tenant_id: str, document_id: str) -> int:
        return self.store.delete_document(tenant_id=tenant_id, document_id=document_id)

    async def answer_query(self, req: RAGQueryRequest) -> RAGQueryResponse:
        started = time.perf_counter()

        # Blocker #5: Prompt Injection Guardrail check before LLM invocation
        is_injected, injection_reason = PromptInjectionGuardrail.inspect(req.query)
        if is_injected:
            is_ru = bool(re.search(r"[а-яА-ЯёЁ]", req.query))
            safe_msg = (
                "Запрос содержит недопустимые системные инструкции и был заблокирован защитным фильтром PulseDesk Guardrail. Диалог передан оператору."
                if is_ru
                else "Your request triggered our security guardrail and cannot be processed automatically. A human operator has been notified."
            )
            elapsed_ms = max(1, int((time.perf_counter() - started) * 1000))
            return RAGQueryResponse(
                answer=safe_msg,
                confidence_score=0.0,
                intent="complaint",
                needs_escalation=True,
                escalation_reason=injection_reason,
                sources=[],
                provider_used="guardrail-shield",
                model_used="pulsedesk-guardrail-v1",
                latency_ms=elapsed_ms,
            )

        intent = self.classify_intent(req.query)

        retrieved = self.store.search(
            tenant_id=req.tenant_id,
            query=req.query,
            top_k=req.top_k,
        )
        sources = [
            SourceCitation(
                document_id=item.document_id,
                title=item.title,
                category=item.category,
                excerpt=item.excerpt,
                score=item.score,
            )
            for item in retrieved
            if item.score > 0.15
        ]

        top_score = sources[0].score if sources else 0.0
        confidence_score = round(top_score, 2)

        needs_escalation = False
        escalation_reason: Optional[str] = None

        if intent == "human_request":
            needs_escalation = True
            escalation_reason = "Customer explicitly requested a human operator."
            confidence_score = min(confidence_score, 0.25)
        elif intent == "complaint":
            needs_escalation = True
            escalation_reason = "High-priority complaint or legal risk detected."
            confidence_score = min(confidence_score, 0.35)
        elif not sources:
            needs_escalation = True
            escalation_reason = "No matching documents found in tenant knowledge base."
            confidence_score = 0.15
        elif confidence_score < req.confidence_threshold:
            needs_escalation = True
            escalation_reason = (
                f"Confidence score ({confidence_score:.2f}) is below threshold ({req.confidence_threshold:.2f})."
            )

        provider = LLMRouterFactory.create(req.llm_config)
        answer, provider_used, model_used = await provider.generate_support_answer(
            query=req.query,
            sources=sources,
            history=req.conversation_history,
            system_prompt=req.llm_config.system_prompt,
        )

        elapsed_ms = max(1, int((time.perf_counter() - started) * 1000))
        return RAGQueryResponse(
            answer=answer,
            confidence_score=confidence_score,
            intent=intent,
            needs_escalation=needs_escalation,
            escalation_reason=escalation_reason,
            sources=sources,
            provider_used=provider_used,
            model_used=model_used,
            latency_ms=elapsed_ms,
        )

    @staticmethod
    def verify_provider(req: ProviderVerifyRequest) -> ProviderVerifyResponse:
        from app.schemas import LLMConfig

        provider_instance = LLMRouterFactory.create(
            LLMConfig(
                provider=req.provider,
                api_key=req.api_key,
                model=req.model,
                base_url=req.base_url,
            )
        )
        has_key = bool(provider_instance.api_key) or req.provider == "mock"
        msg = (
            f"Provider '{req.provider}' configured with model '{provider_instance.model}'."
            if has_key
            else f"Provider '{req.provider}' registered in local fallback mode (no API key supplied)."
        )
        return ProviderVerifyResponse(
            valid=True,
            provider=req.provider,
            model=provider_instance.model,
            message=msg,
        )
