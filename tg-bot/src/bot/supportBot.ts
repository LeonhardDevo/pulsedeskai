import { Bot, InlineKeyboard } from "grammy";
import { config } from "../config.js";
import { RagApiClient } from "../services/ragClient.js";
import { SaasApiClient } from "../services/saasClient.js";

export interface CustomerMessageOutcome {
  replyText: string;
  needsEscalation: boolean;
  confidenceScore: number;
  providerUsed: string;
  sourcesCount: number;
  rateLimited?: boolean;
}

export class ChatRateLimiter {
  private readonly timestamps = new Map<string, number[]>();

  constructor(
    private readonly maxRequests: number = 12,
    private readonly windowMs: number = 60_000
  ) {}

  allow(chatId: string, now: number = Date.now()): boolean {
    const history = (this.timestamps.get(chatId) || []).filter((ts) => now - ts < this.windowMs);
    if (history.length >= this.maxRequests) {
      this.timestamps.set(chatId, history);
      return false;
    }
    history.push(now);
    this.timestamps.set(chatId, history);
    return true;
  }
}

export class SupportDeflectorOrchestrator {
  private readonly chatTenantMap = new Map<string, string>();
  private readonly escalatedChats = new Set<string>();
  private readonly rateLimiter: ChatRateLimiter;

  constructor(
    private readonly ragClient: RagApiClient,
    private readonly saasClient: SaasApiClient,
    private readonly defaultTenantSlug: string = config.defaultTenantSlug,
    rateLimiter?: ChatRateLimiter
  ) {
    this.rateLimiter = rateLimiter || new ChatRateLimiter(12, 60_000);
  }

  setTenantForChat(chatId: string, tenantSlug: string): void {
    this.chatTenantMap.set(chatId, tenantSlug);
  }

  getTenantForChat(chatId: string): string {
    return this.chatTenantMap.get(chatId) || this.defaultTenantSlug;
  }

  isChatEscalated(chatId: string): boolean {
    return this.escalatedChats.has(chatId);
  }

  async handleCustomerQuestion(params: {
    chatId: string;
    customerName: string;
    customerUsername?: string;
    text: string;
    tenantSlugOverride?: string;
  }): Promise<CustomerMessageOutcome> {
    if (params.tenantSlugOverride) {
      this.setTenantForChat(params.chatId, params.tenantSlugOverride);
    }

    if (!this.rateLimiter.allow(params.chatId)) {
      return {
        replyText:
          "⏳ Пожалуйста, подождите минуту перед отправкой следующего вопроса (превышен лимит запросов).",
        needsEscalation: false,
        confidenceScore: 0,
        providerUsed: "rate-limiter",
        sourcesCount: 0,
        rateLimited: true,
      };
    }

    const tenantSlug = this.getTenantForChat(params.chatId);
    const tenant = await this.saasClient.getTenantSettings(tenantSlug);

    if (!tenant.has_quota) {
      this.escalatedChats.add(params.chatId);
      await this.saasClient.escalateTicket(
        tenantSlug,
        params.chatId,
        "Monthly AI support quota reached"
      );
      return {
        replyText:
          "👨‍💻 Ваш запрос передан оператору поддержки. Специалист ответит вам в этом чате в ближайшее время.",
        needsEscalation: true,
        confidenceScore: 0,
        providerUsed: tenant.ai_provider,
        sourcesCount: 0,
      };
    }

    const ragResult = await this.ragClient.queryKnowledgeBase({
      tenant_id: tenant.tenant_id,
      query: params.text,
      confidence_threshold: tenant.confidence_threshold,
      llm_config: {
        provider: tenant.ai_provider,
        api_key: tenant.ai_api_key,
        model: tenant.ai_model,
        system_prompt: tenant.system_prompt,
      },
    });

    if (ragResult.needs_escalation) {
      this.escalatedChats.add(params.chatId);
    }

    await this.saasClient.syncConversationTurn({
      tenant_slug: tenantSlug,
      telegram_chat_id: params.chatId,
      customer_name: params.customerName,
      customer_username: params.customerUsername,
      customer_message: params.text,
      ai_response: ragResult.answer,
      confidence_score: ragResult.confidence_score,
      intent: ragResult.intent,
      needs_escalation: ragResult.needs_escalation,
      provider_used: ragResult.provider_used,
      sources: ragResult.sources,
    });

    const citationsFooter =
      ragResult.sources.length > 0
        ? `\n\n📚 Источник: ${ragResult.sources.map((s) => s.title).join(", ")}`
        : "";

    const escalationFooter = ragResult.needs_escalation
      ? "\n\n👨‍💻 Уверенность ответа ниже порога или требуется специалист — диалог переведён на живого оператора."
      : "";

    return {
      replyText: `${ragResult.answer}${citationsFooter}${escalationFooter}`,
      needsEscalation: ragResult.needs_escalation,
      confidenceScore: ragResult.confidence_score,
      providerUsed: ragResult.provider_used,
      sourcesCount: ragResult.sources.length,
    };
  }

  async escalateManually(chatId: string): Promise<string> {
    const tenantSlug = this.getTenantForChat(chatId);
    this.escalatedChats.add(chatId);
    await this.saasClient.escalateTicket(
      tenantSlug,
      chatId,
      "Customer clicked 'Call Human Agent' button in Telegram"
    );
    return "👨‍💻 Мы подключили живого оператора к вашему диалогу! Напишите детали, и специалист ответит прямо здесь.";
  }

  async markResolvedByCustomer(chatId: string): Promise<string> {
    const tenantSlug = this.getTenantForChat(chatId);
    this.escalatedChats.delete(chatId);
    await this.saasClient.resolveTicket(tenantSlug, chatId);
    return "✅ Спасибо за обращение! Рады, что смогли помочь. Если появятся новые вопросы — просто напишите в чат.";
  }
}

export function createTelegramBot(
  token: string,
  orchestrator: SupportDeflectorOrchestrator
): Bot {
  const bot = new Bot(token || "000000000:AAH_placeholder_token_for_tests");

  bot.command("start", async (ctx) => {
    const chatId = String(ctx.chat.id);
    const payload = (ctx.match || "").trim();
    if (payload.startsWith("tenant_")) {
      const slug = payload.replace(/^tenant_/, "");
      orchestrator.setTenantForChat(chatId, slug);
    }

    await ctx.reply(
      "👋 Здравствуйте! Я AI-ассистент поддержки первой линии.\n\n" +
        "Задайте любой вопрос по заказам, доставке, возврату или характеристикам товаров — я мгновенно отвечу по базе знаний магазина или соединю вас с оператором."
    );
  });

  bot.on("message:text", async (ctx) => {
    const chatId = String(ctx.chat.id);
    const customerName =
      [ctx.from?.first_name, ctx.from?.last_name].filter(Boolean).join(" ") || "Telegram User";

    const outcome = await orchestrator.handleCustomerQuestion({
      chatId,
      customerName,
      customerUsername: ctx.from?.username,
      text: ctx.message.text,
    });

    const keyboard = new InlineKeyboard()
      .text("✅ Вопрос решён", `resolve:${chatId}`)
      .text("👨‍💻 Позвать оператора", `escalate:${chatId}`);

    await ctx.reply(outcome.replyText, { reply_markup: keyboard });
  });

  bot.callbackQuery(/^escalate:(.+)$/, async (ctx) => {
    const chatId = ctx.match[1];
    const msg = await orchestrator.escalateManually(chatId);
    await ctx.answerCallbackQuery({ text: "Переводим на оператора..." });
    await ctx.reply(msg);
  });

  bot.callbackQuery(/^resolve:(.+)$/, async (ctx) => {
    const chatId = ctx.match[1];
    const msg = await orchestrator.markResolvedByCustomer(chatId);
    await ctx.answerCallbackQuery({ text: "Тикет закрыт!" });
    await ctx.reply(msg);
  });

  return bot;
}
