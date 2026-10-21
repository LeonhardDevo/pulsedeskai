<?php

namespace App\Services;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Http;

class TelegramBridgeService
{
    private string $botWebhookUrl;
    private string $internalSecret;

    public function __construct()
    {
        $this->botWebhookUrl = rtrim((string) config('services.pulsedesk.tg_bot_url', 'http://localhost:3001'), '/');
        $this->internalSecret = (string) config('services.pulsedesk.internal_secret', 'pulsedesk_internal_secret_key');
    }

    public function sendOperatorReply(SupportTicket $ticket, string $operatorName, string $message): bool
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'X-Internal-Secret' => $this->internalSecret,
                ])
                ->post("{$this->botWebhookUrl}/internal/operator-reply", [
                    'telegram_chat_id' => $ticket->telegram_chat_id,
                    'operator_name' => $operatorName,
                    'message' => $message,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
