from fastapi import APIRouter

from app.schemas import (
    DocumentIngestRequest,
    DocumentIngestResponse,
    ProviderVerifyRequest,
    ProviderVerifyResponse,
    RAGQueryRequest,
    RAGQueryResponse,
)
from app.services.llm_router import LLMRouterFactory
from app.services.rag_engine import RAGEngine

router = APIRouter()
rag_engine = RAGEngine()


@router.get("/health")
async def health_check() -> dict:
    return {
        "status": "ok",
        "service": "pulsedesk-rag-api",
        "vector_backend": rag_engine.store.backend_name,
        "supported_providers": list(LLMRouterFactory.SUPPORTED_PROVIDERS),
    }


@router.post("/api/v1/documents/ingest", response_model=DocumentIngestResponse)
async def ingest_document(payload: DocumentIngestRequest) -> DocumentIngestResponse:
    return rag_engine.ingest_document(payload)


@router.delete("/api/v1/documents/{tenant_id}/{document_id}")
async def delete_document(tenant_id: str, document_id: str) -> dict:
    deleted = rag_engine.delete_document(tenant_id=tenant_id, document_id=document_id)
    return {
        "status": "deleted",
        "tenant_id": tenant_id,
        "document_id": document_id,
        "deleted_chunks": deleted,
    }


@router.post("/api/v1/rag/query", response_model=RAGQueryResponse)
async def query_rag(payload: RAGQueryRequest) -> RAGQueryResponse:
    return await rag_engine.answer_query(payload)


@router.post("/api/v1/providers/verify", response_model=ProviderVerifyResponse)
async def verify_provider(payload: ProviderVerifyRequest) -> ProviderVerifyResponse:
    return rag_engine.verify_provider(payload)
