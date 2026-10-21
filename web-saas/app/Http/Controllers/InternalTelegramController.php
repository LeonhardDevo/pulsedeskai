<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalTelegramController extends Controller
{
    private function authorizeInternalRequest(Request $request): void
    {
        $expected = (string) config('services.pulsedesk.internal_secret', 'pulsedesk_internal_secret_key');
        $provided = (string) $request->header('X-Internal-Secret', '');

        abort_unless(hash_equals($expected, $provided), 401, 'Unauthorized internal service request.');
    }

    public function tenantSettings(Request $request, string $slug): JsonResponse
    {
        $this->authorizeInternalRequest($request);

        $tenant = Tenant::where('slug', $slug)->first() ?? Tenant::firstOrFail();

        return response()->json([
            'tenant_id' => $tenant->slug,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'ai_provider' => $tenant->ai_provider,
            'ai_api_key' => $tenant->ai_api_key,
            'ai_model' => $tenant->ai_model,
            'confidence_threshold' => $tenant->confidence_threshold,
            'system_prompt' => $tenant->system_prompt,
            'has_quota' => $tenant->hasAvailableQuota(),
        ]);
    }

    public function syncMessage(Request $request): JsonResponse
    {
        $this->authorizeInternalRequest($request);

        $validated = $request->validate([
            'tenant_slug' => ['required', 'string'],
            'telegram_chat_id' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
            'customer_username' => ['nullable', 'string'],
            'customer_message' => ['required', 'string'],
            'ai_response' => ['nullable', 'string'],
            'confidence_score' => ['nullable', 'numeric'],
            'intent' => ['nullable', 'string'],
            'needs_escalation' => ['nullable', 'boolean'],
            'provider_used' => ['nullable', 'string'],
            'sources' => ['nullable', 'array'],
        ]);

        $tenant = Tenant::where('slug', $validated['tenant_slug'])->first() ?? Tenant::firstOrFail();
        $needsEscalation = (bool) ($validated['needs_escalation'] ?? false);
        $confidence = (float) ($validated['confidence_score'] ?? 0.85);

        $ticket = SupportTicket::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'telegram_chat_id' => $validated['telegram_chat_id'],
            ],
            [
                'customer_name' => $validated['customer_name'],
                'customer_username' => $validated['customer_username'] ?? null,
                'status' => $needsEscalation ? 'escalated' : 'ai_handling',
                'was_deflected' => !$needsEscalation,
                'intent' => $validated['intent'] ?? 'faq_question',
                'avg_confidence' => $confidence,
                'last_message_at' => now(),
            ]
        );

        $ticket->update([
            'status' => $needsEscalation ? 'escalated' : $ticket->status,
            'was_deflected' => $needsEscalation ? false : $ticket->was_deflected,
            'intent' => $validated['intent'] ?? $ticket->intent,
            'avg_confidence' => $confidence,
            'last_message_at' => now(),
        ]);

        $ticket->messages()->create([
            'sender_type' => 'customer',
            'sender_name' => $validated['customer_name'],
            'content' => $validated['customer_message'],
        ]);

        if (!empty($validated['ai_response'])) {
            $ticket->messages()->create([
                'sender_type' => 'ai',
                'sender_name' => 'PulseDesk AI (' . ($validated['provider_used'] ?? $tenant->ai_provider) . ')',
                'content' => $validated['ai_response'],
                'confidence_score' => $confidence,
                'provider_used' => $validated['provider_used'] ?? $tenant->ai_provider,
                'sources' => $validated['sources'] ?? [],
            ]);
        }

        $tenant->increment('used_quota');

        return response()->json([
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'was_deflected' => $ticket->was_deflected,
        ]);
    }

    public function escalate(Request $request): JsonResponse
    {
        $this->authorizeInternalRequest($request);

        $validated = $request->validate([
            'tenant_slug' => ['required', 'string'],
            'telegram_chat_id' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::where('slug', $validated['tenant_slug'])->first() ?? Tenant::firstOrFail();

        $ticket = SupportTicket::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'telegram_chat_id' => $validated['telegram_chat_id'],
            ],
            [
                'customer_name' => 'Telegram Customer',
                'status' => 'escalated',
                'was_deflected' => false,
                'last_message_at' => now(),
            ]
        );

        $ticket->update([
            'status' => 'escalated',
            'was_deflected' => false,
            'escalation_reason' => $validated['reason'] ?? 'Escalated to live support operator',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'status' => 'escalated',
            'ticket_id' => $ticket->id,
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $this->authorizeInternalRequest($request);

        $validated = $request->validate([
            'tenant_slug' => ['required', 'string'],
            'telegram_chat_id' => ['required', 'string'],
        ]);

        $tenant = Tenant::where('slug', $validated['tenant_slug'])->first() ?? Tenant::firstOrFail();

        $ticket = SupportTicket::where('tenant_id', $tenant->id)
            ->where('telegram_chat_id', $validated['telegram_chat_id'])
            ->first();

        if ($ticket) {
            $ticket->update([
                'status' => 'resolved',
                'last_message_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'resolved',
        ]);
    }
}
