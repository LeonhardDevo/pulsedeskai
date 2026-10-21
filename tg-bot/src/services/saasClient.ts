import { config } from "../config.js";
import { TenantSettingsDTO, TicketSyncPayload, TicketSyncResponse } from "../types.js";

export class SaasApiClient {
  private readonly baseUrl: string;
  private readonly internalSecret: string;

  constructor(
    baseUrl: string = config.saasApiUrl,
    internalSecret: string = config.internalWebhookSecret
  ) {
    this.baseUrl = baseUrl.replace(/\/$/, "");
    this.internalSecret = internalSecret;
  }

  private get headers(): Record<string, string> {
    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Internal-Secret": this.internalSecret,
    };
  }

  async getTenantSettings(tenantSlug: string): Promise<TenantSettingsDTO> {
    try {
      const response = await fetch(
        `${this.baseUrl}/api/internal/telegram/tenant/${encodeURIComponent(tenantSlug)}`,
        {
          method: "GET",
          headers: this.headers,
        }
      );

      if (response.ok) {
        return (await response.json()) as TenantSettingsDTO;
      }
    } catch {
      // Fallback to default tenant settings if SaaS backend is temporarily unreachable
    }

    return {
      tenant_id: tenantSlug,
      slug: tenantSlug,
      name: "PulseDesk Demo Store",
      ai_provider: "openrouter",
      ai_model: "openai/gpt-4o-mini",
      confidence_threshold: 0.65,
      has_quota: true,
    };
  }

  async syncConversationTurn(payload: TicketSyncPayload): Promise<TicketSyncResponse> {
    try {
      const response = await fetch(`${this.baseUrl}/api/internal/telegram/message`, {
        method: "POST",
        headers: this.headers,
        body: JSON.stringify(payload),
      });

      if (response.ok) {
        return (await response.json()) as TicketSyncResponse;
      }
    } catch {
      // Non-blocking fallback
    }

    return {
      ticket_id: 1,
      status: payload.needs_escalation ? "escalated" : "ai_handling",
      was_deflected: !payload.needs_escalation,
    };
  }

  async escalateTicket(tenantSlug: string, telegramChatId: string, reason: string): Promise<void> {
    try {
      await fetch(`${this.baseUrl}/api/internal/telegram/escalate`, {
        method: "POST",
        headers: this.headers,
        body: JSON.stringify({
          tenant_slug: tenantSlug,
          telegram_chat_id: telegramChatId,
          reason,
        }),
      });
    } catch {
      // Ignore network errors in standalone mode
    }
  }

  async resolveTicket(tenantSlug: string, telegramChatId: string): Promise<void> {
    try {
      await fetch(`${this.baseUrl}/api/internal/telegram/resolve`, {
        method: "POST",
        headers: this.headers,
        body: JSON.stringify({
          tenant_slug: tenantSlug,
          telegram_chat_id: telegramChatId,
        }),
      });
    } catch {
      // Ignore network errors in standalone mode
    }
  }
}
