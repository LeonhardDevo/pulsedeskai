<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $billingService
    ) {}

    public function checkout(Request $request): JsonResponse
    {
        $role = $request->header('X-Workspace-Role')
            ?? ($request->hasSession() ? $request->session()->get('workspace_role') : null)
            ?? 'admin';

        abort_if($role !== 'admin', 403, 'Forbidden: Only Merchant Admins can manage Stripe Billing.');

        $validated = $request->validate([
            'plan' => ['required', 'in:starter,growth,scale'],
        ]);

        $slug = $request->header('X-Tenant-Slug')
            ?? ($request->hasSession() ? $request->session()->get('active_tenant_slug') : null);
        $tenant = ($slug ? Tenant::where('slug', $slug)->first() : null) ?? Tenant::firstOrFail();

        $session = $this->billingService->createCheckoutSession(
            tenant: $tenant,
            planId: $validated['plan'],
            successUrl: url('/?billing=success&plan=' . $validated['plan']),
            cancelUrl: url('/?billing=canceled')
        );

        return response()->json([
            'status' => 'ok',
            'checkout' => $session,
            'tenant_plan' => $tenant->fresh()->plan,
            'monthly_quota' => $tenant->fresh()->monthly_quota,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = (string) config('services.stripe.webhook_secret', '');

        if (!$this->billingService->verifyWebhookSignature($payload, $signature, $webhookSecret)) {
            return response()->json(['error' => 'Invalid Stripe webhook signature'], 400);
        }

        $event = json_decode($payload, true) ?: [];
        $result = $this->billingService->handleWebhookEvent($event);

        return response()->json($result);
    }
}
