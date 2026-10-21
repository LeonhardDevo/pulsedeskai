export type LLMProviderType = "openrouter" | "opencode" | "claude" | "openai" | "mock";

export interface SourceCitation {
  document_id: string;
  title: string;
  category: string;
  excerpt: string;
  score: number;
}

export interface RAGQueryPayload {
  tenant_id: string;
  query: string;
  conversation_history?: Array<{ role: "user" | "assistant" | "system"; content: string }>;
  confidence_threshold?: number;
  llm_config?: {
    provider: LLMProviderType;
    api_key?: string | null;
    model?: string | null;
    system_prompt?: string | null;
  };
}

export interface RAGQueryResult {
  answer: string;
  confidence_score: number;
  intent: string;
  needs_escalation: boolean;
  escalation_reason?: string | null;
  sources: SourceCitation[];
  provider_used: string;
  model_used: string;
  latency_ms: number;
}

export interface TenantSettingsDTO {
  tenant_id: string;
  slug: string;
  name: string;
  ai_provider: LLMProviderType;
  ai_api_key?: string | null;
  ai_model?: string | null;
  confidence_threshold: number;
  system_prompt?: string | null;
  has_quota: boolean;
}

export interface TicketSyncPayload {
  tenant_slug: string;
  telegram_chat_id: string;
  customer_name: string;
  customer_username?: string;
  customer_message: string;
  ai_response?: string;
  confidence_score?: number;
  intent?: string;
  needs_escalation?: boolean;
  provider_used?: string;
  sources?: SourceCitation[];
}

export interface TicketSyncResponse {
  ticket_id: number;
  status: "ai_handling" | "escalated" | "resolved";
  was_deflected: boolean;
}
