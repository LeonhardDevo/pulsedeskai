import dotenv from "dotenv";

dotenv.config();

export interface BotConfig {
  port: number;
  telegramBotToken: string;
  defaultTenantSlug: string;
  ragApiUrl: string;
  saasApiUrl: string;
  internalWebhookSecret: string;
}

export const config: BotConfig = {
  port: Number(process.env.PORT || 3001),
  telegramBotToken: process.env.TELEGRAM_BOT_TOKEN || "",
  defaultTenantSlug: process.env.DEFAULT_TENANT_SLUG || "demo-store",
  ragApiUrl: (process.env.RAG_API_URL || "http://localhost:8001").replace(/\/$/, ""),
  saasApiUrl: (process.env.SAAS_API_URL || "http://localhost:8000").replace(/\/$/, ""),
  internalWebhookSecret: process.env.INTERNAL_WEBHOOK_SECRET || "pulsedesk_internal_secret_key",
};
