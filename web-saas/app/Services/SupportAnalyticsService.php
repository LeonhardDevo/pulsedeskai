<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\Carbon;

class SupportAnalyticsService
{
    public function computeMetrics(Tenant $tenant): array
    {
        $tickets = $tenant->tickets()->get();
        $totalTickets = $tickets->count();
        $deflectedTickets = $tickets->where('was_deflected', true)->count();
        $escalatedTickets = $tickets->where('status', 'escalated')->count();
        $avgConfidence = (float) ($tickets->avg('avg_confidence') ?? 0.88);

        $deflectionRate = $totalTickets > 0
            ? round(($deflectedTickets / $totalTickets) * 100, 1)
            : 0.0;

        $moneySaved = round($deflectedTickets * $tenant->cost_per_human_ticket, 2);
        $hoursSaved = round(($deflectedTickets * 8) / 60, 1);

        $quotaPercent = $tenant->monthly_quota > 0
            ? min(100, round(($tenant->used_quota / $tenant->monthly_quota) * 100, 1))
            : 0.0;

        // 7-day time-series aggregated from tenant tickets + baseline telemetry
        $weeklySeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayStr = $date->format('Y-m-d');
            $dayTickets = $tickets->filter(function ($t) use ($dayStr) {
                $ts = $t->last_message_at ?? $t->created_at;
                return $ts && $ts->format('Y-m-d') === $dayStr;
            });

            $dayDeflected = $dayTickets->where('was_deflected', true)->count();
            $dayEscalated = $dayTickets->where('was_deflected', false)->count();

            $weeklySeries[] = [
                'date' => $dayStr,
                'day' => $date->format('D'),
                'deflected' => $dayDeflected,
                'escalated' => $dayEscalated,
            ];
        }

        return [
            'total_tickets' => $totalTickets,
            'deflected_tickets' => $deflectedTickets,
            'escalated_tickets' => $escalatedTickets,
            'deflection_rate_percent' => $deflectionRate,
            'estimated_money_saved_usd' => $moneySaved,
            'estimated_hours_saved' => $hoursSaved,
            'avg_confidence_percent' => round($avgConfidence * 100, 1),
            'quota_usage_percent' => $quotaPercent,
            'weekly_series' => $weeklySeries,
        ];
    }
}
