<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class StripeBillingService
{
    public const PLANS = [
        'starter' => [
            'id' => 'starter',
            'name' => 'Starter',
            'price_usd' => 29,
            'monthly_quota' => 500,
            'features' => [
                '500 AI-deflected Telegram conversations / mo',
                'BYOK: OpenRouter, OpenCode, Claude, OpenAI',
                'PostgreSQL + pgvector RAG Knowledge Base',
            ],
        ],
        'growth' => [
            'id' => 'growth',
            'name' => 'Growth',
            'price_usd' => 79,
            'monthly_quota' => 3000,
            'features' => [
                '3,000 AI-deflected Telegram conversations / mo',
                'Human-in-the-Loop Live Operator Inbox',
                'Confidence Score Tuning & Custom Prompts',
            ],
        ],
        'scale' => [
            'id' => 'scale',
            'name' => 'Scale Enterprise',
            'price_usd' => 199,
            'monthly_quota' => 15000,
            'features' => [
                '15,000 AI-deflected Telegram conversations / mo',
                'Priority Multi-LLM Failover Routing',
                'Dedicated SLA & Unlimited Knowledge Docs',
            ],
        ],
    ];

    public function createCheckoutSession(Tenant $tenant, string $planId, string $successUrl, string $cancelUrl): array
    {
        if (!isset(self::PLANS[$planId])) {
            throw new InvalidArgumentException("Invalid subscription plan: {$planId}");
        }

        $plan = self::PLANS[$planId];
        $stripeSecret = (string) config('services.stripe.secret', '');

        // When a real Stripe secret key is configured, call Stripe's Checkout Sessions API
        if ($stripeSecret !== '' && !str_contains($stripeSecret, 'placeholder')) {
            $response = Http::withToken($stripeSecret)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'mode' => 'subscription',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'client_reference_id' => (string) $tenant->id,
                    'metadata[tenant_id]' => (string) $tenant->id,
                    'metadata[plan]' => $planId,
                    'line_items[0][price_data][currency]' => 'usd',
                    'line_items[0][price_data][unit_amount]' => $plan['price_usd'] * 100,
                    'line_items[0][price_data][recurring][interval]' => 'month',
                    'line_items[0][price_data][product_data][name]' => "PulseDesk AI — {$plan['name']} Plan",
                    'line_items[0][quantity]' => 1,
                ]);

            if ($response->successful()) {
                $session = $response->json();
                return [
                    'session_id' => $session['id'],
                    'checkout_url' => $session['url'],
                    'mode' => 'stripe_live',
                ];
            }
        }

        // Sandbox / Demo Instant Upgrade when Stripe key is in placeholder mode
        $tenant->update([
            'plan' => $planId,
            'monthly_quota' => $plan['monthly_quota'],
            'subscription_status' => 'active',
            'stripe_subscription_id' => 'sub_demo_' . $planId . '_' . $tenant->id,
        ]);

        return [
            'session_id' => 'cs_test_demo_' . uniqid(),
            'checkout_url' => $successUrl,
            'mode' => 'sandbox_instant',
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader, string $webhookSecret): bool
    {
        if (empty($webhookSecret) || str_contains($webhookSecret, 'placeholder')) {
            return true;
        }
        if (empty($signatureHeader)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $item) {
            [$k, $v] = array_pad(explode('=', trim($item), 2), 2, '');
            $parts[$k] = $v;
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $signedPayload = $parts['t'] . '.' . $payload;
        $expectedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);
        return hash_equals($expectedSig, $parts['v1']);
    }

    public function handleWebhookEvent(array $event): array
    {
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            $tenantId = $object['metadata']['tenant_id'] ?? $object['client_reference_id'] ?? null;
            $planId = $object['metadata']['plan'] ?? 'growth';
            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            if ($tenant && isset(self::PLANS[$planId])) {
                $tenant->update([
                    'plan' => $planId,
                    'monthly_quota' => self::PLANS[$planId]['monthly_quota'],
                    'stripe_customer_id' => $object['customer'] ?? $tenant->stripe_customer_id,
                    'stripe_subscription_id' => $object['subscription'] ?? $tenant->stripe_subscription_id,
                    'subscription_status' => 'active',
                ]);
            }

            return ['handled' => true, 'event' => $type];
        }

        if ($type === 'customer.subscription.deleted') {
            $subId = $object['id'] ?? null;
            if ($subId) {
                Tenant::where('stripe_subscription_id', $subId)->update([
                    'subscription_status' => 'canceled',
                    'plan' => 'starter',
                    'monthly_quota' => self::PLANS['starter']['monthly_quota'],
                ]);
            }
            return ['handled' => true, 'event' => $type];
        }

        return ['handled' => false, 'event' => $type];
    }
}
