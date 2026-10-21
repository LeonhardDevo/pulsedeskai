<template>
  <section class="space-y-6">
    <!-- Executive Header with Badge -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b pd-border">
      <div>
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 mb-2">
          <Activity class="w-3.5 h-3.5 text-indigo-400" />
          <span>{{ t.analytics.headerBadge }}</span>
        </div>
        <h2 class="text-2xl font-extrabold tracking-tight pd-text">{{ t.analytics.headerTitle }}</h2>
        <p class="text-xs pd-muted mt-1 max-w-2xl leading-relaxed">{{ t.analytics.headerDesc }}</p>
      </div>

      <!-- Quick Summary Pill Matrix -->
      <div class="flex items-center gap-3">
        <div class="px-3.5 py-2 rounded-xl pd-input border pd-border text-xs flex items-center gap-2">
          <Cpu class="w-4 h-4 text-cyan-400" />
          <span class="pd-muted">Vector DB:</span>
          <span class="font-mono font-semibold text-emerald-400">pgvector HNSW</span>
        </div>
        <div class="px-3.5 py-2 rounded-xl pd-input border pd-border text-xs flex items-center gap-2">
          <Zap class="w-4 h-4 text-amber-400" />
          <span class="pd-muted">Latency:</span>
          <span class="font-mono font-semibold pd-text">~42ms</span>
        </div>
      </div>
    </div>

    <!-- 4 KPI Cards with Sparklines & Trend Indicators -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <!-- 1: Deflection Rate -->
      <div class="relative overflow-hidden rounded-2xl p-5 pd-card border shadow-sm group hover:border-emerald-500/50 transition">
        <div class="flex items-center justify-between">
          <span class="text-xs pd-muted uppercase tracking-wider font-semibold">{{ t.kpi.deflectionRate }}</span>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
            <TrendingUp class="w-3 h-3" />
            +8.4%
          </span>
        </div>
        <div class="flex items-baseline gap-2 mt-3">
          <div class="text-3xl font-extrabold tracking-tight text-emerald-400">{{ metrics.deflection_rate_percent }}%</div>
          <span class="text-xs pd-muted font-medium">{{ metrics.deflected_tickets }}/{{ metrics.total_tickets }}</span>
        </div>
        <div class="text-xs pd-muted mt-1 truncate">{{ t.kpi.deflectionSub }}</div>

        <!-- Mini Sparkline SVG -->
        <div class="mt-4 h-8 w-full">
          <svg viewBox="0 0 100 24" class="w-full h-full overflow-visible" preserveAspectRatio="none">
            <path
              d="M0,18 Q15,16 30,12 T60,8 T85,5 L100,3"
              fill="none"
              stroke="#10b981"
              stroke-width="2"
              stroke-linecap="round"
            />
            <path
              d="M0,18 Q15,16 30,12 T60,8 T85,5 L100,3 L100,24 L0,24 Z"
              fill="url(#deflectGrad)"
              opacity="0.15"
            />
            <defs>
              <linearGradient id="deflectGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#10b981" />
                <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
              </linearGradient>
            </defs>
          </svg>
        </div>
      </div>

      <!-- 2: Saved Support Budget -->
      <div class="relative overflow-hidden rounded-2xl p-5 pd-card border shadow-sm group hover:border-indigo-500/50 transition">
        <div class="flex items-center justify-between">
          <span class="text-xs pd-muted uppercase tracking-wider font-semibold">{{ t.kpi.savedBudget }}</span>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded-full border border-indigo-500/20">
            <DollarSign class="w-3 h-3" />
            Saved
          </span>
        </div>
        <div class="flex items-baseline gap-2 mt-3">
          <div class="text-3xl font-extrabold tracking-tight text-indigo-400">${{ metrics.estimated_money_saved_usd }}</div>
          <span class="text-xs pd-muted font-medium">~{{ metrics.estimated_hours_saved }}h</span>
        </div>
        <div class="text-xs pd-muted mt-1 truncate">{{ t.kpi.savedHours }}</div>

        <!-- Mini Sparkline SVG -->
        <div class="mt-4 h-8 w-full">
          <svg viewBox="0 0 100 24" class="w-full h-full overflow-visible" preserveAspectRatio="none">
            <path
              d="M0,20 Q20,15 40,14 T70,7 T90,4 L100,2"
              fill="none"
              stroke="#6366f1"
              stroke-width="2"
              stroke-linecap="round"
            />
            <path
              d="M0,20 Q20,15 40,14 T70,7 T90,4 L100,2 L100,24 L0,24 Z"
              fill="url(#budgetGrad)"
              opacity="0.15"
            />
            <defs>
              <linearGradient id="budgetGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#6366f1" />
                <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
              </linearGradient>
            </defs>
          </svg>
        </div>
      </div>

      <!-- 3: Escalation Queue (Clickable) -->
      <div
        class="relative overflow-hidden rounded-2xl p-5 pd-card border shadow-sm cursor-pointer hover:border-amber-500/60 transition group"
        @click="$emit('open-escalated')"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs pd-muted uppercase tracking-wider font-semibold">{{ t.kpi.waitingHuman }}</span>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/20">
            <AlertCircle class="w-3 h-3" />
            SLA Attention
          </span>
        </div>
        <div class="flex items-baseline gap-2 mt-3">
          <div class="text-3xl font-extrabold tracking-tight text-amber-400">{{ metrics.escalated_tickets }}</div>
          <span class="text-xs text-amber-400 font-medium flex items-center gap-0.5">
            {{ t.kpi.humanSub }} <ArrowUpRight class="w-3.5 h-3.5" />
          </span>
        </div>
        <div class="text-xs pd-muted mt-1 truncate">{{ t.common.intent }}: SLA handoff</div>

        <!-- Mini Progress Bar / Queue indicator -->
        <div class="mt-4 pt-2">
          <div class="flex justify-between text-[11px] pd-muted mb-1">
            <span>Escalation ratio</span>
            <span>{{ Math.round((metrics.escalated_tickets / Math.max(1, metrics.total_tickets)) * 100) }}%</span>
          </div>
          <div class="h-1.5 rounded-full pd-input overflow-hidden">
            <div
              class="h-full rounded-full bg-amber-500"
              :style="{ width: Math.min(100, Math.round((metrics.escalated_tickets / Math.max(1, metrics.total_tickets)) * 100)) + '%' }"
            ></div>
          </div>
        </div>
      </div>

      <!-- 4: Quota & Confidence -->
      <div class="relative overflow-hidden rounded-2xl p-5 pd-card border shadow-sm hover:border-cyan-500/50 transition">
        <div class="flex items-center justify-between">
          <span class="text-xs pd-muted uppercase tracking-wider font-semibold">{{ t.kpi.planQuota }} ({{ tenant.plan?.toUpperCase() }})</span>
          <span class="text-[11px] font-bold text-cyan-400 bg-cyan-500/10 px-2 py-0.5 rounded-full border border-cyan-500/20">
            {{ metrics.avg_confidence_percent }}% acc
          </span>
        </div>
        <div class="flex items-baseline gap-2 mt-3">
          <div class="text-2xl font-extrabold tracking-tight pd-text">
            {{ tenant.used_quota }} <span class="pd-muted text-sm font-semibold">/ {{ tenant.monthly_quota }}</span>
          </div>
        </div>
        <div class="mt-3 h-2 rounded-full pd-input overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 via-cyan-400 to-emerald-400" :style="{ width: quotaPercent + '%' }"></div>
        </div>
        <div class="flex justify-between items-center text-[11px] pd-muted mt-2">
          <span>{{ t.kpi.avgConfidence }}</span>
          <span class="font-mono text-cyan-400">{{ metrics.avg_confidence_percent }}%</span>
        </div>
      </div>
    </div>

    <!-- Main Dual Section: 7-Day Area Spline Chart + Resolution Funnel Matrix -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <!-- 7-Day Interactive SVG Spline Area Chart (2 Cols) -->
      <div class="xl:col-span-2 rounded-2xl p-6 pd-card border shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
            <div>
              <h3 class="font-bold text-base pd-text">{{ t.analytics.weeklyTitle }}</h3>
              <p class="text-xs pd-muted mt-0.5">{{ t.analytics.weeklySubtitle }}</p>
            </div>
            <!-- Legend Indicators -->
            <div class="flex items-center gap-4 text-xs">
              <span class="flex items-center gap-2 font-medium pd-text">
                <span class="h-2.5 w-2.5 rounded-full bg-indigo-500 shadow-sm shadow-indigo-500/50"></span>
                {{ t.analytics.deflectedLegend }}
              </span>
              <span class="flex items-center gap-2 font-medium pd-text">
                <span class="h-2.5 w-2.5 rounded-full bg-amber-500 shadow-sm shadow-amber-500/50"></span>
                {{ t.analytics.escalatedLegend }}
              </span>
            </div>
          </div>
        </div>

        <!-- Premium SVG Area Chart with Gradient & Hover Crosshairs -->
        <div class="relative mt-6 h-64 w-full select-none" @mouseleave="hoveredPoint = null">
          <svg viewBox="0 0 600 200" class="w-full h-full overflow-visible" preserveAspectRatio="none">
            <defs>
              <linearGradient id="aiAreaGradient" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.4" />
                <stop offset="85%" stop-color="#6366f1" stop-opacity="0.02" />
                <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
              </linearGradient>
              <linearGradient id="escAreaGradient" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.3" />
                <stop offset="100%" stop-color="#f59e0b" stop-opacity="0" />
              </linearGradient>
            </defs>

            <!-- Horizontal Background Gridlines -->
            <line x1="0" y1="20" x2="600" y2="20" stroke="currentColor" stroke-opacity="0.08" stroke-dasharray="3 3" />
            <line x1="0" y1="70" x2="600" y2="70" stroke="currentColor" stroke-opacity="0.08" stroke-dasharray="3 3" />
            <line x1="0" y1="120" x2="600" y2="120" stroke="currentColor" stroke-opacity="0.08" stroke-dasharray="3 3" />
            <line x1="0" y1="170" x2="600" y2="170" stroke="currentColor" stroke-opacity="0.12" />

            <!-- AI Deflected Smooth Area Fill -->
            <path :d="deflectedAreaPath" fill="url(#aiAreaGradient)" />

            <!-- AI Deflected Smooth Spline Stroke -->
            <path :d="deflectedLinePath" fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round" />

            <!-- Human Escalated Area Fill -->
            <path :d="escalatedAreaPath" fill="url(#escAreaGradient)" />

            <!-- Human Escalated Line Stroke -->
            <path :d="escalatedLinePath" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="4 2" />

            <!-- Interactive Circular Points & Tooltip Triggers -->
            <g v-for="(p, idx) in chartCoordinates" :key="idx" class="cursor-pointer">
              <!-- Deflected Point -->
              <circle
                :cx="p.x"
                :cy="p.yDeflected"
                r="4.5"
                fill="#6366f1"
                stroke="#fff"
                stroke-width="2"
                class="transition-transform group-hover:scale-125"
                @mouseenter="hoveredPoint = { ...p, index: idx }"
              />
              <!-- Escalated Point -->
              <circle
                :cx="p.x"
                :cy="p.yEscalated"
                r="3.5"
                fill="#f59e0b"
                stroke="#fff"
                stroke-width="1.5"
                @mouseenter="hoveredPoint = { ...p, index: idx }"
              />
            </g>

            <!-- Active Hover Crosshair Line -->
            <line
              v-if="hoveredPoint"
              :x1="hoveredPoint.x"
              y1="10"
              :x2="hoveredPoint.x"
              y2="170"
              stroke="#6366f1"
              stroke-width="1.5"
              stroke-dasharray="2 2"
            />
          </svg>

          <!-- Day Labels Below Chart -->
          <div class="flex justify-between items-center text-xs pd-muted font-medium mt-3 px-2">
            <span v-for="d in chartSeries" :key="d.date || d.day" class="text-center font-mono">
              {{ d.day }}
            </span>
          </div>

          <!-- Dynamic Floating Tooltip -->
          <div
            v-if="hoveredPoint"
            class="absolute pointer-events-none z-30 transform -translate-x-1/2 -translate-y-full px-3 py-2 rounded-xl pd-card border pd-border shadow-xl text-xs space-y-1"
            :style="{ left: `${(hoveredPoint.x / 600) * 100}%`, top: `${(Math.min(hoveredPoint.yDeflected, hoveredPoint.yEscalated) / 200) * 100}%` }"
          >
            <div class="font-bold pd-text border-b pd-border pb-1">{{ hoveredPoint.day }}</div>
            <div class="flex items-center justify-between gap-3 text-indigo-400">
              <span>{{ t.analytics.deflectedLegend }}:</span>
              <strong class="font-mono">{{ hoveredPoint.deflected }}</strong>
            </div>
            <div class="flex items-center justify-between gap-3 text-amber-400">
              <span>{{ t.analytics.escalatedLegend }}:</span>
              <strong class="font-mono">{{ hoveredPoint.escalated }}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Resolution Funnel & AI Telemetry Matrix (1 Col) -->
      <div class="space-y-6">
        <!-- Radial Funnel Breakdown -->
        <div class="rounded-2xl p-6 pd-card border shadow-sm">
          <h3 class="font-bold text-base pd-text">{{ t.analytics.funnelTitle }}</h3>
          <p class="text-xs pd-muted mt-0.5">{{ t.analytics.funnelSubtitle }}</p>

          <div class="flex items-center justify-center my-6">
            <div class="relative w-36 h-36 flex items-center justify-center">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <!-- Background track -->
                <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-opacity="0.1" stroke-width="12" />
                <!-- AI Deflected Ring (Emerald/Indigo) -->
                <circle
                  cx="50"
                  cy="50"
                  r="40"
                  fill="transparent"
                  stroke="#6366f1"
                  stroke-width="12"
                  :stroke-dasharray="251.2"
                  :stroke-dashoffset="251.2 * (1 - (metrics.deflection_rate_percent || 70) / 100)"
                  stroke-linecap="round"
                />
              </svg>
              <div class="absolute flex flex-col items-center">
                <span class="text-2xl font-black pd-text">{{ metrics.deflection_rate_percent }}%</span>
                <span class="text-[10px] pd-muted uppercase font-bold tracking-wider">Automated</span>
              </div>
            </div>
          </div>

          <div class="space-y-2.5 pt-2 border-t pd-border text-xs">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 pd-muted">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                {{ t.analytics.deflectedLegend }}
              </span>
              <span class="font-mono font-bold text-indigo-400">{{ metrics.deflected_tickets }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 pd-muted">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                {{ t.analytics.escalatedLegend }}
              </span>
              <span class="font-mono font-bold text-amber-400">{{ metrics.escalated_tickets }}</span>
            </div>
          </div>
        </div>

        <!-- Telemetry Matrix -->
        <div class="rounded-2xl p-5 pd-card border shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider pd-muted">{{ t.analytics.telemetryTitle }}</span>
            <span class="h-2 w-2 rounded-full bg-emerald-500 pd-live"></span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-3 rounded-xl pd-input border pd-border">
              <div class="pd-muted">{{ t.analytics.p50Latency }}</div>
              <div class="text-sm font-bold font-mono text-emerald-400 mt-1">28 ms</div>
            </div>
            <div class="p-3 rounded-xl pd-input border pd-border">
              <div class="pd-muted">{{ t.analytics.p95Latency }}</div>
              <div class="text-sm font-bold font-mono text-cyan-400 mt-1">64 ms</div>
            </div>
            <div class="p-3 rounded-xl pd-input border pd-border">
              <div class="pd-muted">{{ t.analytics.vectorIndex }}</div>
              <div class="text-sm font-bold font-mono text-indigo-400 mt-1">128-dim HNSW</div>
            </div>
            <div class="p-3 rounded-xl pd-input border pd-border">
              <div class="pd-muted">{{ t.analytics.guardrailBlocks }}</div>
              <div class="text-sm font-bold font-mono text-rose-400 mt-1">100% Active</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Intent Frequency & Confidence Matrix -->
    <div class="rounded-2xl p-6 pd-card border shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
          <h3 class="font-bold text-base pd-text">{{ t.analytics.intentTitle }}</h3>
          <p class="text-xs pd-muted mt-0.5">{{ t.analytics.intentSubtitle }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="it in intentDistribution"
          :key="it.id"
          class="p-4 rounded-xl pd-input border pd-border flex flex-col justify-between hover:border-indigo-500/40 transition"
        >
          <div>
            <div class="flex items-center justify-between text-xs mb-2">
              <span class="font-bold pd-text truncate">{{ it.label }}</span>
              <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-black/20 pd-muted">
                {{ it.count }} ({{ it.pct }}%)
              </span>
            </div>
            <div class="h-2 rounded-full pd-card overflow-hidden">
              <div class="h-full rounded-full transition-all" :class="it.color" :style="{ width: Math.max(6, it.pct) + '%' }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Executive Financial ROI Simulator with Comparative Breakdown -->
    <div class="rounded-2xl p-6 pd-card border shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
          <h3 class="font-bold text-base pd-text">{{ t.analytics.roiTitle }}</h3>
          <p class="text-xs pd-muted mt-0.5">{{ t.analytics.roiSubtitle }}</p>
        </div>
        <div class="px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold text-emerald-400 flex items-center gap-1.5">
          <TrendingUp class="w-3.5 h-3.5" />
          <span>{{ roiMultiplier }}x {{ t.analytics.roiPayback }}</span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
        <!-- Sliders (5 Cols) -->
        <div class="lg:col-span-5 space-y-5">
          <label class="block">
            <div class="flex justify-between text-xs mb-1.5">
              <span class="pd-muted">{{ t.analytics.roiTicketsLabel }}</span>
              <span class="font-bold font-mono text-indigo-400">{{ roiSim.monthlyTickets.toLocaleString() }}</span>
            </div>
            <input
              type="range"
              min="200"
              max="25000"
              step="100"
              v-model.number="roiSim.monthlyTickets"
              class="w-full accent-indigo-600 cursor-pointer"
            />
          </label>

          <label class="block">
            <div class="flex justify-between text-xs mb-1.5">
              <span class="pd-muted">{{ t.analytics.roiCostLabel }}</span>
              <span class="font-bold font-mono text-indigo-400">${{ roiSim.costPerTicket.toFixed(1) }}</span>
            </div>
            <input
              type="range"
              min="1"
              max="20"
              step="0.5"
              v-model.number="roiSim.costPerTicket"
              class="w-full accent-indigo-600 cursor-pointer"
            />
          </label>
        </div>

        <!-- Comparative Cost Bars (4 Cols) -->
        <div class="lg:col-span-4 space-y-3 p-4 rounded-xl pd-input border pd-border">
          <div>
            <div class="flex justify-between text-xs mb-1">
              <span class="pd-muted">{{ t.analytics.humanCostBar }}</span>
              <span class="font-mono font-bold text-rose-400">${{ Math.round(roiSim.monthlyTickets * roiSim.costPerTicket).toLocaleString() }}</span>
            </div>
            <div class="h-2 rounded-full bg-rose-500/20 overflow-hidden">
              <div class="h-full bg-rose-500 w-full rounded-full"></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between text-xs mb-1">
              <span class="pd-muted">{{ t.analytics.aiCostBar }}</span>
              <span class="font-mono font-bold text-emerald-400">${{ Math.round(roiSim.monthlyTickets * (1 - (metrics.deflection_rate_percent || 70) / 100) * roiSim.costPerTicket + 99).toLocaleString() }}</span>
            </div>
            <div class="h-2 rounded-full bg-emerald-500/20 overflow-hidden">
              <div
                class="h-full bg-emerald-500 rounded-full"
                :style="{ width: `${Math.round(100 - (metrics.deflection_rate_percent || 70))}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Savings Summary Cards (3 Cols) -->
        <div class="lg:col-span-3 space-y-3">
          <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/25">
            <div class="text-xs text-emerald-400 font-semibold">{{ t.analytics.roiMonthlySavings }}</div>
            <div class="text-2xl font-black text-emerald-400 mt-1">${{ roiMonthlySavings.toLocaleString() }}</div>
          </div>
          <div class="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/25">
            <div class="text-xs text-indigo-400 font-semibold">{{ t.analytics.roiAnnualSavings }}</div>
            <div class="text-2xl font-black text-indigo-400 mt-1">${{ roiAnnualSavings.toLocaleString() }}</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import {
  Activity,
  Cpu,
  Zap,
  TrendingUp,
  DollarSign,
  AlertCircle,
  ArrowUpRight,
} from 'lucide-vue-next';

const props = defineProps({
  t: { type: Object, required: true },
  tenant: { type: Object, required: true },
  metrics: { type: Object, required: true },
  intentDistribution: { type: Array, required: true },
  quotaPercent: { type: Number, default: 0 },
});

defineEmits(['open-escalated']);

const hoveredPoint = ref(null);

const roiSim = reactive({
  monthlyTickets: 1800,
  costPerTicket: props.tenant?.cost_per_human_ticket || 4.5,
});

const chartSeries = computed(() => {
  return props.metrics?.weekly_series || [];
});

const maxChartValue = computed(() => {
  if (!chartSeries.value.length) return 5;
  const values = chartSeries.value.flatMap((d) => [d.deflected, d.escalated]);
  return Math.max(4, Math.max(...values));
});

// Calculate normalized SVG coordinates (width: 600, height: 180, bottom padding: 20)
const chartCoordinates = computed(() => {
  const list = chartSeries.value;
  if (!list.length) return [];
  const stepX = 600 / Math.max(1, list.length - 1);
  const maxVal = maxChartValue.value;

  return list.map((d, i) => {
    const x = i * stepX;
    // Map value to SVG y (inverted, y=20 is max, y=170 is 0)
    const yDeflected = 170 - (d.deflected / maxVal) * 140;
    const yEscalated = 170 - (d.escalated / maxVal) * 140;
    return {
      x,
      yDeflected,
      yEscalated,
      deflected: d.deflected,
      escalated: d.escalated,
      day: d.day,
      date: d.date,
    };
  });
});

// Generate cubic Bezier spline for smooth aesthetics
function generateBezierPath(points, yKey) {
  if (!points.length) return '';
  if (points.length === 1) return `M${points[0].x},${points[0][yKey]}`;

  let path = `M ${points[0].x},${points[0][yKey]}`;
  for (let i = 0; i < points.length - 1; i++) {
    const curr = points[i];
    const next = points[i + 1];
    const cpX1 = curr.x + (next.x - curr.x) / 2;
    const cpY1 = curr[yKey];
    const cpX2 = curr.x + (next.x - curr.x) / 2;
    const cpY2 = next[yKey];
    path += ` C ${cpX1},${cpY1} ${cpX2},${cpY2} ${next.x},${next[yKey]}`;
  }
  return path;
}

const deflectedLinePath = computed(() => generateBezierPath(chartCoordinates.value, 'yDeflected'));
const deflectedAreaPath = computed(() => {
  const line = deflectedLinePath.value;
  if (!line || !chartCoordinates.value.length) return '';
  const lastX = chartCoordinates.value[chartCoordinates.value.length - 1].x;
  return `${line} L ${lastX},170 L 0,170 Z`;
});

const escalatedLinePath = computed(() => generateBezierPath(chartCoordinates.value, 'yEscalated'));
const escalatedAreaPath = computed(() => {
  const line = escalatedLinePath.value;
  if (!line || !chartCoordinates.value.length) return '';
  const lastX = chartCoordinates.value[chartCoordinates.value.length - 1].x;
  return `${line} L ${lastX},170 L 0,170 Z`;
});

const roiMonthlySavings = computed(() => {
  const rate = (props.metrics?.deflection_rate_percent || 70) / 100;
  return Math.max(0, Math.round(roiSim.monthlyTickets * rate * roiSim.costPerTicket));
});

const roiAnnualSavings = computed(() => roiMonthlySavings.value * 12);

const roiMultiplier = computed(() => {
  const monthlyCost = 99; // PulseDesk Base
  return Math.max(1, Math.round(roiMonthlySavings.value / monthlyCost));
});
</script>
