from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.routes import router
from app.config import settings


def create_app() -> FastAPI:
    application = FastAPI(
        title=settings.app_name,
        description=(
            "PulseDesk AI Customer Support Deflector — Hybrid RAG Engine & Multi-LLM Router "
            "(OpenRouter, OpenCode, Claude, OpenAI)"
        ),
        version="1.0.0",
    )

    application.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    application.include_router(router)
    return application


app = create_app()
