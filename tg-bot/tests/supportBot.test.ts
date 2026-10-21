import { describe, expect, it, vi } from "vitest";
import request from "supertest";
import { ChatRateLimiter, SupportDeflectorOrchestrator } from "../src/bot/supportBot.js";
import { RagApiClient } from "../src/services/ragClient.js";
import { SaasApiClient } from "../src/services/saasClient.js";
import { createWebhookServer } from "../src/server/webhookServer.js";
import { config } from "../src/config.js";

describe("PulseDesk TG Bot — SupportDeflectorOrchestrator, RateLimiter & Multi-Tenant Webhook", () => {
  it("deflects common FAQ question using tenant's configured provider (openrouter)", async () => {
    const mockRagClient = {
      queryKnowledgeBase: vi.fn().mockResolvedValue({
        answer: "Возврат возможен в течение 14 дней при сохранении чека.",
        confidence_score: 0.91,
        intent: "return_refund",
        needs_escalation: false,
        sources: [
          {
            document_id: "doc_1",
            title: "Правила возврата",
            category: "return_policy",
            excerpt: "14 дней на возврат.",
            score: 0.91,
          },
        ],
        provider_used: "openrouter",
        model_used: "openai/gpt-4o-mini",
        latency_ms: 42,
      }),
    } as unknown as RagApiClient;

    const mockSaasClient = {
      getTenantSettings: vi.fn().mockResolvedValue({
        tenant_id: "tenant_1",
        slug: "demo-store",
        name: "Demo Store",
        ai_provider: "openrouter",
        ai_model: "openai/gpt-4o-mini",
        confidence_threshold: 0.65,
        has_quota: true,
      }),
      syncConversationTurn: vi.fn().mockResolvedValue({
        ticket_id: 10,
        status: "ai_handling",
        was_deflected: true,
      }),
      escalateTicket: vi.fn(),
      resolveTicket: vi.fn(),
    } as unknown as SaasApiClient;

    const orchestrator = new SupportDeflectorOrchestrator(mockRagClient, mockSaasClient);
    const outcome = await orchestrator.handleCustomerQuestion({
      chatId: "998877",
      customerName: "Alexey",
      text: "Как вернуть товар?",
    });

    expect(outcome.needsEscalation).toBe(false);
    expect(outcome.confidenceScore).toBe(0.91);
    expect(outcome.providerUsed).toBe("openrouter");
    expect(outcome.replyText).toContain("14 дней");
  });

  it("enforces per-chat sliding window rate limiting to protect merchant API keys", async () => {
    const mockRagClient = { queryKnowledgeBase: vi.fn() } as unknown as RagApiClient;
    const mockSaasClient = { getTenantSettings: vi.fn() } as unknown as SaasApiClient;
    const strictLimiter = new ChatRateLimiter(2, 60_000);
    const orchestrator = new SupportDeflectorOrchestrator(
      mockRagClient,
      mockSaasClient,
      "demo-store",
      strictLimiter
    );

    expect(strictLimiter.allow("chat_flood")).toBe(true);
    expect(strictLimiter.allow("chat_flood")).toBe(true);

    const blockedOutcome = await orchestrator.handleCustomerQuestion({
      chatId: "chat_flood",
      customerName: "Spammer",
      text: "Third message in same second",
    });

    expect(blockedOutcome.rateLimited).toBe(true);
    expect(blockedOutcome.providerUsed).toBe("rate-limiter");
    expect(mockRagClient.queryKnowledgeBase).not.toHaveBeenCalled();
  });

  it("handles dynamic multi-tenant Telegram webhooks (/webhook/telegram/:tenantSlug) and operator replies", async () => {
    const sendOperatorMessage = vi.fn().mockResolvedValue(undefined);
    const mockRagClient = {
      queryKnowledgeBase: vi.fn().mockResolvedValue({
        answer: "Доставка СДЭК за 2 дня.",
        confidence_score: 0.88,
        intent: "order_status",
        needs_escalation: false,
        sources: [],
        provider_used: "openrouter",
        model_used: "openai/gpt-4o-mini",
        latency_ms: 20,
      }),
    } as unknown as RagApiClient;
    const mockSaasClient = {
      getTenantSettings: vi.fn().mockResolvedValue({
        tenant_id: " sneaker-shop",
        slug: "sneaker-shop",
        name: "Sneaker Shop",
        ai_provider: "openrouter",
        confidence_threshold: 0.65,
        has_quota: true,
      }),
      syncConversationTurn: vi.fn().mockResolvedValue({
        ticket_id: 55,
        status: "ai_handling",
        was_deflected: true,
      }),
    } as unknown as SaasApiClient;

    const orchestrator = new SupportDeflectorOrchestrator(mockRagClient, mockSaasClient);
    const app = createWebhookServer({ sendOperatorMessage }, orchestrator);

    const tgWebhookRes = await request(app)
      .post("/webhook/telegram/sneaker-shop")
      .send({
        message: {
          chat: { id: 445566 },
          from: { first_name: "Ivan", username: "ivan_k" },
          text: "Сколько идет доставка?",
        },
      });

    expect(tgWebhookRes.status).toBe(200);
    expect(tgWebhookRes.body.ok).toBe(true);
    expect(tgWebhookRes.body.tenant_slug).toBe("sneaker-shop");
    expect(tgWebhookRes.body.outcome.replyText).toContain("СДЭК");

    const okRes = await request(app)
      .post("/internal/operator-reply")
      .set("X-Internal-Secret", config.internalWebhookSecret)
      .send({
        telegram_chat_id: "998877",
        operator_name: "Dmitry (Senior Support)",
        message: "Здравствуйте! Мы уже отправили замену по вашему заказу #4021.",
      });

    expect(okRes.status).toBe(200);
    expect(okRes.body.status).toBe("delivered");
  });
});
