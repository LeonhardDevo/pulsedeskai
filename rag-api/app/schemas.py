from typing import Literal, Optional
from pydantic import BaseModel, Field

ProviderType = Literal["openrouter", "opencode", "claude", "openai", "mock"]
DocumentCategory = Literal["faq", "return_policy", "catalog", "shipping", "general"]
CustomerIntent = Literal[
    "faq_question",
    "order_status",
    "return_refund",
    "product_compatibility",
    "human_request",
    "complaint",
]


class LLMConfig(BaseModel):
    provider: ProviderType = Field(
        default="openrouter",
        description="LLM provider selected by the tenant: openrouter, opencode, claude, openai, or mock",
    )
    api_key: Optional[str] = Field(
        default=None,
        description="Tenant-specific BYOK API key for the selected provider",
    )
    model: Optional[str] = Field(
        default=None,
        description="Model identifier (e.g. openai/gpt-4o-mini, claude-3-5-sonnet-latest, gpt-4o-mini)",
    )
    base_url: Optional[str] = Field(
        default=None,
        description="Optional custom base URL override for OpenRouter / OpenCode / compatible endpoints",
    )
    system_prompt: Optional[str] = Field(
        default=None,
        description="Custom merchant instructions for tone of voice and support rules",
    )


class ChatMessage(BaseModel):
    role: Literal["user", "assistant", "system"]
    content: str


class DocumentIngestRequest(BaseModel):
    tenant_id: str = Field(..., min_length=1, description="Unique tenant identifier")
    document_id: str = Field(..., min_length=1, description="Document ID from Laravel SaaS")
    title: str = Field(..., min_length=1, description="Document title")
    category: DocumentCategory = Field(default="faq")
    content: str = Field(..., min_length=5, description="Raw Markdown/Text/FAQ content to chunk and index")
    chunk_size: int = Field(default=500, ge=100, le=2000)
    chunk_overlap: int = Field(default=80, ge=0, le=500)


class DocumentIngestResponse(BaseModel):
    status: str = "indexed"
    tenant_id: str
    document_id: str
    title: str
    chunks_count: int
    vector_backend: str


class SourceCitation(BaseModel):
    document_id: str
    title: str
    category: str
    excerpt: str
    score: float


class RAGQueryRequest(BaseModel):
    tenant_id: str = Field(..., min_length=1)
    query: str = Field(..., min_length=1)
    conversation_history: list[ChatMessage] = Field(default_factory=list)
    top_k: int = Field(default=3, ge=1, le=10)
    confidence_threshold: float = Field(default=0.65, ge=0.1, le=0.99)
    llm_config: LLMConfig = Field(default_factory=LLMConfig)


class RAGQueryResponse(BaseModel):
    answer: str
    confidence_score: float
    intent: CustomerIntent
    needs_escalation: bool
    escalation_reason: Optional[str] = None
    sources: list[SourceCitation] = Field(default_factory=list)
    provider_used: str
    model_used: str
    latency_ms: int


class ProviderVerifyRequest(BaseModel):
    provider: ProviderType
    api_key: Optional[str] = None
    model: Optional[str] = None
    base_url: Optional[str] = None


class ProviderVerifyResponse(BaseModel):
    valid: bool
    provider: str
    model: str
    message: str
