<template>
  <section class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="relative rounded-2xl p-6 flex flex-col pd-card border shadow-sm transition"
        :class="tenant.plan === plan.id ? 'border-indigo-500 pd-glow' : 'pd-border'"
      >
        <span
          v-if="tenant.plan === plan.id"
          class="absolute -top-3 left-6 text-xs px-3 py-1 rounded-full bg-indigo-600 text-white font-semibold flex items-center gap-1"
        >
          <ShieldCheck class="w-3 h-3" />
          {{ t.billing.currentPlan }}
        </span>
        <h3 class="font-bold text-lg pd-text">{{ plan.name }}</h3>
        <div class="mt-3 flex items-baseline gap-1">
          <span class="text-4xl font-extrabold pd-text">${{ plan.price_usd }}</span>
          <span class="text-sm pd-muted">{{ t.billing.perMonth }}</span>
        </div>
        <ul class="mt-5 space-y-3 text-sm pd-text flex-1">
          <li v-for="feat in plan.features" :key="feat" class="flex gap-2">
            <Check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
            <span>{{ feat }}</span>
          </li>
        </ul>
        <button
          type="button"
          @click="$emit('upgrade-plan', plan.id)"
          :disabled="tenant.plan === plan.id"
          class="mt-6 w-full py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer disabled:cursor-default flex items-center justify-center gap-2"
          :class="tenant.plan === plan.id ? 'pd-input border pd-border pd-muted' : 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white hover:from-indigo-500 hover:to-violet-500'"
        >
          <CreditCard class="w-4 h-4" />
          <span>{{ tenant.plan === plan.id ? t.billing.activeBtn : t.billing.upgradeBtn }}</span>
        </button>
      </div>
    </div>

    <div class="rounded-2xl px-5 py-4 pd-card border text-xs pd-muted flex gap-3 items-center">
      <Lock class="w-4 h-4 text-indigo-400 shrink-0" />
      <span>{{ t.billing.webhookInfo }}</span>
    </div>
  </section>
</template>

<script setup>
import { Check, CreditCard, Lock, ShieldCheck } from 'lucide-vue-next';

defineProps({
  t: { type: Object, required: true },
  tenant: { type: Object, required: true },
  plans: { type: Object, required: true },
});

defineEmits(['upgrade-plan']);
</script>
