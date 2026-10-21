from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "PulseDesk RAG Engine"
    app_env: str = "local"
    port: int = 8001

    database_url: str = "sqlite:///./rag_local.db"
    use_pgvector: bool = False

    # Default LLM Provider settings
    default_llm_provider: str = "openrouter"
    default_llm_model: str = "openai/gpt-4o-mini"

    openrouter_api_key: str = ""
    openrouter_base_url: str = "https://openrouter.ai/api/v1"

    opencode_api_key: str = ""
    opencode_base_url: str = "https://api.opencode.ai/v1"

    claude_api_key: str = ""
    claude_base_url: str = "https://api.anthropic.com/v1"

    openai_api_key: str = ""
    openai_base_url: str = "https://api.openai.com/v1"

    default_confidence_threshold: float = 0.65

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")


settings = Settings()
