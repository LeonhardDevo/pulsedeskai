import express, { Express, Request, Response } from "express";
import { config } from "../config.js";
import { SupportDeflectorOrchestrator } from "../bot/supportBot.js";

export interface TelegramMessageSender {
  sendOperatorMessage(chatId: string, operatorName: string, message: string): Promise<void>;
}

export function createWebhookServer(
  sender: TelegramMessageSender,
  orchestrator?: SupportDeflectorOrchestrator
): Express {
  const app = express();
  app.use(express.json());

  app.get("/health", (_req: Request, res: Response) => {
    res.json({
      status: "ok",
      service: "pulsedesk-tg-bot",
      mode: "multi-tenant-webhook+polling",
      default_tenant: config.defaultTenantSlug,
    });
  });

  /**
   * Multi-tenant Telegram Webhook endpoint (Blocker #2):
   * Allows a single Node.js instance to handle incoming Telegram updates
   * for any merchant store (`POST /webhook/telegram/:tenantSlug`).
   */
  app.post("/webhook/telegram/:tenantSlug", async (req: Request, res: Response) => {
    const tenantSlug = String(req.params.tenantSlug || config.defaultTenantSlug);
    const update = req.body as {
      message?: {
        chat?: { id?: number | string };
        from?: { first_name?: string; last_name?: string; username?: string };
        text?: string;
      };
    };

    const msg = update?.message;
    if (!msg?.chat?.id || !msg?.text || !orchestrator) {
      res.json({ ok: true, skipped: true });
      return;
    }

    const chatId = String(msg.chat.id);
    const customerName =
      [msg.from?.first_name, msg.from?.last_name].filter(Boolean).join(" ") || "Telegram Buyer";

    const outcome = await orchestrator.handleCustomerQuestion({
      chatId,
      customerName,
      customerUsername: msg.from?.username,
      text: msg.text,
      tenantSlugOverride: tenantSlug,
    });

    res.json({
      ok: true,
      tenant_slug: tenantSlug,
      outcome,
    });
  });

  /**
   * Internal webhook invoked by Laravel SaaS when a live operator sends a reply
   * to an escalated ticket from the Vue 3 Operator Workspace.
   */
  app.post("/internal/operator-reply", async (req: Request, res: Response) => {
    const secret = req.header("X-Internal-Secret");
    if (secret !== config.internalWebhookSecret) {
      res.status(401).json({ error: "Unauthorized internal webhook call" });
      return;
    }

    const { telegram_chat_id, operator_name, message } = req.body as {
      telegram_chat_id?: string;
      operator_name?: string;
      message?: string;
    };

    if (!telegram_chat_id || !message) {
      res.status(422).json({ error: "telegram_chat_id and message are required" });
      return;
    }

    await sender.sendOperatorMessage(
      String(telegram_chat_id),
      operator_name || "Support Specialist",
      message
    );

    res.json({
      status: "delivered",
      telegram_chat_id: String(telegram_chat_id),
    });
  });

  return app;
}
