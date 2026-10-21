import { config } from "./config.js";
import { RagApiClient } from "./services/ragClient.js";
import { SaasApiClient } from "./services/saasClient.js";
import { createTelegramBot, SupportDeflectorOrchestrator } from "./bot/supportBot.js";
import { createWebhookServer } from "./server/webhookServer.js";

async function bootstrap() {
  const ragClient = new RagApiClient(config.ragApiUrl);
  const saasClient = new SaasApiClient(config.saasApiUrl, config.internalWebhookSecret);
  const orchestrator = new SupportDeflectorOrchestrator(ragClient, saasClient);
  const bot = createTelegramBot(config.telegramBotToken, orchestrator);

  const app = createWebhookServer(
    {
      async sendOperatorMessage(chatId: string, operatorName: string, message: string) {
        if (!config.telegramBotToken) {
          console.log(`[Mock TG Delivery -> ${chatId}] (${operatorName}): ${message}`);
          return;
        }
        await bot.api.sendMessage(
          chatId,
          `👨‍💻 **Оператор (${operatorName}):**\n${message}`,
          { parse_mode: "Markdown" }
        );
      },
    },
    orchestrator
  );

  app.listen(config.port, () => {
    console.log(`[PulseDesk TG-Bot] Multi-tenant webhook server listening on port ${config.port}`);
  });

  if (config.telegramBotToken) {
    bot.start({
      onStart: (botInfo) => {
        console.log(`[PulseDesk TG-Bot] Telegram polling active as @${botInfo.username}`);
      },
    });
  } else {
    console.log(
      "[PulseDesk TG-Bot] TELEGRAM_BOT_TOKEN not set — running in multi-tenant webhook/bridge mode."
    );
  }
}

bootstrap().catch((err) => {
  console.error("[PulseDesk TG-Bot] Fatal startup error:", err);
  process.exit(1);
});
