<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PulseDeskSaasTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_html_contains_zero_external_cdn_links(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('/build/assets/', $html);
    }

    public function test_dashboard_json_returns_seeded_tenant_and_deflection_metrics(): void
    {
        $response = $this->getJson('/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('tenant.slug', 'demo-store')
            ->assertJsonPath('tenant.ai_provider', 'openrouter')
            ->assertJsonStructure([
                'tenant',
                'metrics' => [
                    'total_tickets',
                    'deflected_tickets',
                    'escalated_tickets',
                    'deflection_rate_percent',
                    'estimated_money_saved_usd',
                ],
                'documents',
                'tickets',
                'supported_providers',
                'plans',
            ]);
    }

    public function test_merchant_can_switch_between_openrouter_opencode_claude_and_openai(): void
    {
        $this->getJson('/dashboard');

        foreach ([
            ['provider' => 'openrouter', 'model' => 'openai/gpt-4o-mini', 'key' => 'sk-or-v1-secret12345'],
            ['provider' => 'opencode', 'model' => 'opencode-chat-v1', 'key' => 'oc-live-secret12345'],
            ['provider' => 'claude', 'model' => 'claude-3-5-sonnet-latest', 'key' => 'sk-ant-secret12345'],
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'key' => 'sk-proj-secret12345'],
        ] as $cfg) {
            $res = $this->postJson('/settings/ai', [
                'ai_provider' => $cfg['provider'],
                'ai_model' => $cfg['model'],
                'ai_api_key' => $cfg['key'],
                'confidence_threshold' => 0.72,
            ]);

            $res->assertStatus(200)
                ->assertJsonPath('tenant.ai_provider', $cfg['provider'])
                ->assertJsonPath('tenant.ai_model', $cfg['model'])
                ->assertJsonPath('tenant.has_api_key', true);
        }
    }

    public function test_knowledge_base_document_is_indexed_and_reindexed_in_fastapi_rag_service(): void
    {
        Http::fake([
            '*/api/v1/documents/ingest' => Http::response([
                'status' => 'indexed',
                'tenant_id' => 'demo-store',
                'document_id' => '99',
                'chunks_count' => 3,
                'vector_backend' => 'postgresql+pgvector',
            ], 200),
        ]);

        $this->getJson('/dashboard');

        $response = $this->postJson('/documents', [
            'title' => 'Условия VIP-доставки',
            'category' => 'shipping',
            'content' => 'Экспресс-доставка по Москве осуществляется за 3 часа курьером.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('document.title', 'Условия VIP-доставки')
            ->assertJsonPath('document.chunks_count', 3)
            ->assertJsonPath('document.status', 'indexed');

        $doc = KnowledgeDocument::latest('id')->firstOrFail();
        $reindexRes = $this->postJson("/documents/{$doc->id}/reindex");
        $reindexRes->assertStatus(200)
            ->assertJsonPath('status', 'reindexed');
    }

    public function test_telegram_bot_sync_status_toggle_and_live_operator_reply_workflow(): void
    {
        Http::fake([
            '*/internal/operator-reply' => Http::response(['status' => 'delivered'], 200),
        ]);

        $this->getJson('/dashboard');
        $secret = config('services.pulsedesk.internal_secret');

        // 1. Node.js TG Bot syncs an escalated customer turn
        $syncRes = $this->withHeaders(['X-Internal-Secret' => $secret])
            ->postJson('/api/internal/telegram/message', [
                'tenant_slug' => 'demo-store',
                'telegram_chat_id' => '555000111',
                'customer_name' => 'Иван Петров',
                'customer_message' => 'Нужен живой оператор по корпоративному заказу!',
                'ai_response' => 'Перевожу диалог на специалиста.',
                'confidence_score' => 0.25,
                'intent' => 'human_request',
                'needs_escalation' => true,
                'provider_used' => 'openrouter',
            ]);

        $syncRes->assertStatus(200)
            ->assertJsonPath('status', 'escalated')
            ->assertJsonPath('was_deflected', false);

        $ticketId = $syncRes->json('ticket_id');
        $ticket = SupportTicket::findOrFail($ticketId);

        // 2. Operator toggles status to ai_handling and back
        $statusRes = $this->postJson("/tickets/{$ticket->id}/status", [
            'status' => 'ai_handling',
        ]);
        $statusRes->assertStatus(200)
            ->assertJsonPath('ticket.status', 'ai_handling');

        // 3. Support operator replies from Vue 3 Inbox -> triggers Node.js TG Bot webhook
        $replyRes = $this->postJson("/tickets/{$ticket->id}/reply", [
            'operator_name' => 'Мария (Старший менеджер)',
            'message' => 'Здравствуйте, Иван! Подготовили для вас счет со скидкой 15%.',
        ]);

        $replyRes->assertStatus(200)
            ->assertJsonPath('status', 'replied')
            ->assertJsonPath('telegram_delivered', true)
            ->assertJsonPath('ticket.status', 'resolved');
    }

    public function test_stripe_billing_checkout_and_webhook_subscription_upgrade(): void
    {
        $this->getJson('/dashboard');
        $tenant = Tenant::firstOrFail();

        $checkoutRes = $this->postJson('/billing/checkout', [
            'plan' => 'scale',
        ]);

        $checkoutRes->assertStatus(200)
            ->assertJsonPath('tenant_plan', 'scale')
            ->assertJsonPath('monthly_quota', 15000);

        $webhookRes = $this->postJson('/api/stripe/webhook', [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'client_reference_id' => (string) $tenant->id,
                    'customer' => 'cus_stripe_999',
                    'subscription' => 'sub_stripe_999',
                    'metadata' => [
                        'tenant_id' => (string) $tenant->id,
                        'plan' => 'growth',
                    ],
                ],
            ],
        ]);

        $webhookRes->assertStatus(200)
            ->assertJsonPath('handled', true);

        $this->assertEquals('growth', $tenant->fresh()->plan);
        $this->assertEquals('cus_stripe_999', $tenant->fresh()->stripe_customer_id);
    }

    public function test_system_health_endpoint_returns_service_states(): void
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $res = $this->getJson('/system/health');
        $res->assertStatus(200)
            ->assertJsonPath('web_saas', 'online')
            ->assertJsonPath('rag_api', 'online')
            ->assertJsonPath('tg_bot', 'online');
    }

    public function test_api_keys_are_encrypted_at_rest_and_operators_cannot_modify_admin_resources(): void
    {
        $this->getJson('/dashboard');

        // Admin saves an API key
        $plainKey = 'sk-or-v1-super-secret-production-key-9999';
        $this->postJson('/settings/ai', [
            'ai_provider' => 'openrouter',
            'ai_model' => 'openai/gpt-4o-mini',
            'ai_api_key' => $plainKey,
            'confidence_threshold' => 0.70,
        ])->assertStatus(200);

        // Verify raw SQLite/PostgreSQL value is AES-256 encrypted ciphertext, NOT plaintext
        $rawCiphertext = \Illuminate\Support\Facades\DB::table('tenants')
            ->where('slug', 'demo-store')
            ->value('ai_api_key');

        $this->assertNotEquals($plainKey, $rawCiphertext);
        $this->assertEquals($plainKey, Tenant::where('slug', 'demo-store')->firstOrFail()->ai_api_key);

        // Switch workspace role to Support Operator
        $this->postJson('/workspace/role', ['role' => 'operator'])
            ->assertStatus(200)
            ->assertJsonPath('current_role', 'operator');

        // Operator is blocked (403 Forbidden) from modifying AI keys, KB documents, or Stripe billing
        $this->withHeaders(['X-Workspace-Role' => 'operator'])
            ->postJson('/settings/ai', [
                'ai_provider' => 'openai',
                'ai_model' => 'gpt-4o-mini',
                'confidence_threshold' => 0.5,
            ])->assertStatus(403);

        $this->withHeaders(['X-Workspace-Role' => 'operator'])
            ->postJson('/documents', [
                'title' => 'Unauthorized Doc',
                'category' => 'faq',
                'content' => 'Operators should not be able to mutate vector store.',
            ])->assertStatus(403);

        $this->withHeaders(['X-Workspace-Role' => 'operator'])
            ->postJson('/billing/checkout', ['plan' => 'scale'])
            ->assertStatus(403);
    }

    public function test_multi_tenant_switcher_isolates_tenant_context_and_weekly_db_series(): void
    {
        $this->getJson('/dashboard');

        $switchRes = $this->postJson('/workspace/tenant', [
            'tenant_slug' => 'cloudsaas-pro',
        ]);
        $switchRes->assertStatus(200)
            ->assertJsonPath('active_tenant_slug', 'cloudsaas-pro');

        $dashRes = $this->withHeaders(['X-Tenant-Slug' => 'cloudsaas-pro'])
            ->getJson('/dashboard');

        $dashRes->assertStatus(200)
            ->assertJsonPath('tenant.slug', 'cloudsaas-pro')
            ->assertJsonPath('tenant.ai_provider', 'claude');

        $this->assertCount(7, $dashRes->json('metrics.weekly_series'));
    }
}

