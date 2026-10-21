<?php

namespace App\Http\Controllers;

use App\Jobs\IndexKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\RagEngineService;
use App\Services\StripeBillingService;
use App\Services\SupportAnalyticsService;
use App\Services\TelegramBridgeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RagEngineService $ragService,
        private readonly TelegramBridgeService $telegramBridge,
        private readonly SupportAnalyticsService $analyticsService
    ) {}

    private function resolveActiveTenant(Request $request): Tenant
    {
        if (Tenant::count() === 0) {
            app(DatabaseSeeder::class)->run();
        }

        $slug = $request->query('tenant')
            ?? $request->header('X-Tenant-Slug')
            ?? ($request->hasSession() ? $request->session()->get('active_tenant_slug') : null);

        if ($slug) {
            $found = Tenant::where('slug', $slug)->first();
            if ($found) {
                return $found;
            }
        }

        return Tenant::firstOrFail();
    }

    private function resolveCurrentRole(Request $request): string
    {
        $role = $request->header('X-Workspace-Role')
            ?? ($request->hasSession() ? $request->session()->get('workspace_role') : null)
            ?? 'admin';

        return in_array($role, ['admin', 'operator'], true) ? $role : 'admin';
    }

    private function ensureAdminRole(Request $request): void
    {
        abort_if(
            $this->resolveCurrentRole($request) !== 'admin',
            403,
            'Forbidden: Only Merchant Admins can modify AI keys, Knowledge Base, or Stripe Billing.'
        );
    }

    public function index(Request $request): View|JsonResponse
    {
        $tenant = $this->resolveActiveTenant($request);
        $role = $this->resolveCurrentRole($request);

        $payload = [
            'current_role' => $role,
            'tenants_list' => Tenant::select('id', 'name', 'slug', 'plan', 'ai_provider')->get(),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'ai_provider' => $tenant->ai_provider,
                'ai_model' => $tenant->ai_model,
                'masked_api_key' => $role === 'admin' ? $tenant->masked_api_key : '•••••••• (Admin Only)',
                'has_api_key' => !empty($tenant->ai_api_key),
                'confidence_threshold' => $tenant->confidence_threshold,
                'system_prompt' => $tenant->system_prompt,
                'plan' => $tenant->plan,
                'subscription_status' => $tenant->subscription_status,
                'monthly_quota' => $tenant->monthly_quota,
                'used_quota' => $tenant->used_quota,
                'cost_per_human_ticket' => $tenant->cost_per_human_ticket,
                'webhook_url' => url("/webhook/telegram/{$tenant->slug}"),
            ],
            'metrics' => $this->analyticsService->computeMetrics($tenant),
            'documents' => $tenant->documents()->latest()->get(),
            'tickets' => $tenant->tickets()->with('messages')->latest('last_message_at')->get(),
            'supported_providers' => Tenant::SUPPORTED_PROVIDERS,
            'plans' => StripeBillingService::PLANS,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return view('app', ['initialState' => $payload]);
    }

    public function switchRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,operator'],
        ]);

        if ($request->hasSession()) {
            $request->session()->put('workspace_role', $validated['role']);
        }

        return response()->json([
            'status' => 'ok',
            'current_role' => $validated['role'],
        ]);
    }

    public function switchTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_slug' => ['required', 'string', 'exists:tenants,slug'],
        ]);

        if ($request->hasSession()) {
            $request->session()->put('active_tenant_slug', $validated['tenant_slug']);
        }

        return response()->json([
            'status' => 'ok',
            'active_tenant_slug' => $validated['tenant_slug'],
        ]);
    }

    public function systemHealth(): JsonResponse
    {
        $ragUrl = rtrim((string) config('services.pulsedesk.rag_api_url', 'http://127.0.0.1:8011'), '/');
        $botUrl = rtrim((string) config('services.pulsedesk.tg_bot_url', 'http://127.0.0.1:3001'), '/');

        $ragOnline = false;
        $botOnline = false;

        try {
            $ragOnline = Http::timeout(2)->get("{$ragUrl}/health")->successful();
        } catch (\Throwable) {
            $ragOnline = false;
        }

        try {
            $botOnline = Http::timeout(2)->get("{$botUrl}/health")->successful();
        } catch (\Throwable) {
            $botOnline = false;
        }

        return response()->json([
            'web_saas' => 'online',
            'rag_api' => $ragOnline ? 'online' : 'standby',
            'tg_bot' => $botOnline ? 'online' : 'standby',
        ]);
    }

    public function storeDocument(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAdminRole($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:faq,return_policy,catalog,shipping'],
            'content' => ['required', 'string', 'min:10'],
        ]);

        $tenant = $this->resolveActiveTenant($request);

        $document = $tenant->documents()->create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'status' => 'pending',
        ]);

        // Dispatched via Laravel Queue (IndexKnowledgeDocumentJob)
        IndexKnowledgeDocumentJob::dispatchSync($tenant, $document);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'indexed',
                'document' => $document->fresh(),
            ], 201);
        }

        return redirect()->back();
    }

    public function reindexDocument(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->ensureAdminRole($request);

        $tenant = $this->resolveActiveTenant($request);
        abort_unless($document->tenant_id === $tenant->id, 403);

        IndexKnowledgeDocumentJob::dispatchSync($tenant, $document);

        return response()->json([
            'status' => 'reindexed',
            'document' => $document->fresh(),
        ]);
    }

    public function destroyDocument(Request $request, KnowledgeDocument $document): JsonResponse|RedirectResponse
    {
        $this->ensureAdminRole($request);

        $tenant = $this->resolveActiveTenant($request);
        abort_unless($document->tenant_id === $tenant->id, 403);

        $this->ragService->deleteDocument($tenant, $document);
        $document->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->back();
    }

    public function updateAiSettings(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAdminRole($request);

        $validated = $request->validate([
            'ai_provider' => ['required', 'in:openrouter,opencode,claude,openai,mock'],
            'ai_model' => ['required', 'string', 'max:120'],
            'ai_api_key' => ['nullable', 'string', 'max:512'],
            'confidence_threshold' => ['required', 'numeric', 'min:0.1', 'max:0.99'],
            'system_prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant = $this->resolveActiveTenant($request);

        $updates = [
            'ai_provider' => $validated['ai_provider'],
            'ai_model' => $validated['ai_model'],
            'confidence_threshold' => (float) $validated['confidence_threshold'],
            'system_prompt' => $validated['system_prompt'] ?? $tenant->system_prompt,
        ];

        if (array_key_exists('ai_api_key', $validated) && $validated['ai_api_key'] !== null && $validated['ai_api_key'] !== '') {
            $updates['ai_api_key'] = $validated['ai_api_key'];
        }

        $tenant->update($updates);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'updated',
                'tenant' => [
                    'ai_provider' => $tenant->ai_provider,
                    'ai_model' => $tenant->ai_model,
                    'masked_api_key' => $tenant->masked_api_key,
                    'has_api_key' => !empty($tenant->ai_api_key),
                    'confidence_threshold' => $tenant->confidence_threshold,
                    'system_prompt' => $tenant->system_prompt,
                ],
            ]);
        }

        return redirect()->back();
    }

    public function querySandbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $tenant = $this->resolveActiveTenant($request);
        $result = $this->ragService->querySandbox($tenant, $validated['query']);

        return response()->json($result);
    }

    public function replyToTicket(Request $request, SupportTicket $ticket): JsonResponse|RedirectResponse
    {
        $tenant = $this->resolveActiveTenant($request);
        abort_unless($ticket->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'operator_name' => ['nullable', 'string', 'max:100'],
        ]);

        $operatorName = $validated['operator_name'] ?? 'Support Specialist';

        $msg = $ticket->messages()->create([
            'sender_type' => 'operator',
            'sender_name' => $operatorName,
            'content' => $validated['message'],
        ]);

        $ticket->update([
            'status' => 'resolved',
            'was_deflected' => false,
            'last_message_at' => now(),
        ]);

        $delivered = $this->telegramBridge->sendOperatorReply($ticket, $operatorName, $validated['message']);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'replied',
                'telegram_delivered' => $delivered,
                'message' => $msg,
                'ticket' => $ticket->fresh('messages'),
            ]);
        }

        return redirect()->back();
    }

    public function updateTicketStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $tenant = $this->resolveActiveTenant($request);
        abort_unless($ticket->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:ai_handling,escalated,resolved'],
        ]);

        $ticket->update([
            'status' => $validated['status'],
            'was_deflected' => $validated['status'] !== 'escalated',
        ]);

        return response()->json([
            'status' => 'updated',
            'ticket' => $ticket->fresh('messages'),
        ]);
    }
}
