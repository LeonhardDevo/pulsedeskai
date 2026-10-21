<template>
  <aside class="hidden md:flex w-68 shrink-0 flex-col border-r pd-border pd-sidebar sticky top-0 h-screen select-none">
    <!-- Brand Logo -->
    <div class="px-5 py-5 flex items-center gap-3 border-b pd-border">
      <svg viewBox="0 0 64 64" class="h-10 w-10 shrink-0" fill="none" aria-label="PulseDesk AI logo">
        <defs>
          <linearGradient id="pdLogoBg" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#4F46E5" />
            <stop offset="50%" stop-color="#7C3AED" />
            <stop offset="100%" stop-color="#06B6D4" />
          </linearGradient>
        </defs>
        <rect x="4" y="4" width="56" height="56" rx="16" fill="url(#pdLogoBg)" />
        <path d="M18 46L14 54L25 49" fill="url(#pdLogoBg)" />
        <rect x="8" y="8" width="48" height="48" rx="12" stroke="#fff" stroke-opacity=".22" stroke-width="1.5" />
        <path d="M13 33H21L26 20L33 44L39 26L43 33H51" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="51" cy="33" r="3" fill="#22D3EE" />
      </svg>
      <div class="min-w-0">
        <div class="font-extrabold tracking-tight text-[17px] leading-tight pd-text">
          Pulse<span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-500 to-cyan-400">Desk</span> AI
        </div>
        <div class="text-xs pd-muted truncate">{{ t.brandTagline }}</div>
      </div>
    </div>

    <!-- Workspace Role Switcher (Admin Console vs Operator Desk) -->
    <div class="px-3 pt-4 pb-2">
      <div class="p-1 rounded-xl pd-input border pd-border grid grid-cols-2 gap-1">
        <button
          type="button"
          @click="$emit('switch-role', 'admin')"
          class="px-2.5 py-2 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer"
          :class="role === 'admin' ? 'bg-indigo-600 text-white shadow-sm' : 'pd-muted hover:opacity-80'"
        >
          <ShieldCheck class="w-3.5 h-3.5 shrink-0" />
          <span>Admin</span>
        </button>
        <button
          type="button"
          @click="$emit('switch-role', 'operator')"
          class="px-2.5 py-2 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer"
          :class="role === 'operator' ? 'bg-emerald-600 text-white shadow-sm' : 'pd-muted hover:opacity-80'"
        >
          <Headset class="w-3.5 h-3.5 shrink-0" />
          <span>Operator</span>
        </button>
      </div>
      <div class="mt-2 px-2 text-[12px] pd-muted leading-snug">
        {{ role === 'admin' ? t.roles.adminBadge : t.roles.operatorBadge }}
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-3 space-y-1">
      <button
        v-for="item in visibleNavItems"
        :key="item.id"
        type="button"
        @click="$emit('update:activeTab', item.id)"
        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-left cursor-pointer"
        :class="activeTab === item.id ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'pd-muted hover:bg-indigo-500/10 pd-text'"
      >
        <component :is="item.icon" class="w-4 h-4 shrink-0" />
        <span class="flex-1 truncate">{{ item.label }}</span>
        <span
          v-if="item.badge"
          class="text-xs px-2 py-0.5 rounded-full font-semibold"
          :class="activeTab === item.id ? 'bg-white/20 text-white' : (item.muted ? 'bg-slate-500/15 pd-muted' : 'bg-amber-500/20 text-amber-500')"
        >
          {{ item.badge }}
        </span>
      </button>
    </nav>

    <!-- Live Service Health -->
    <div class="m-3 p-4 rounded-2xl pd-input border pd-border space-y-2.5">
      <div class="flex items-center justify-between text-xs">
        <span class="pd-muted">Laravel SaaS</span>
        <span class="flex items-center gap-1.5 text-emerald-500 font-medium">
          <span class="h-2 w-2 rounded-full bg-emerald-500 pd-live"></span>{{ t.status.online }}
        </span>
      </div>
      <div
        v-for="svc in [{ key: 'rag_api', label: t.status.ragApi }, { key: 'tg_bot', label: t.status.tgBot }]"
        :key="svc.key"
        class="flex items-center justify-between text-xs"
      >
        <span class="pd-muted">{{ svc.label }}</span>
        <span class="flex items-center gap-1.5 font-medium" :class="health[svc.key] === 'online' ? 'text-emerald-500' : 'text-amber-500'">
          <span class="h-2 w-2 rounded-full" :class="health[svc.key] === 'online' ? 'bg-emerald-500 pd-live' : 'bg-amber-500'"></span>
          {{ health[svc.key] === 'online' ? t.status.online : t.status.standby }}
        </span>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import {
  ShieldCheck,
  Headset,
  BarChart3,
  MessageSquareText,
  BookOpen,
  Cpu,
  CreditCard,
} from 'lucide-vue-next';

const props = defineProps({
  t: { type: Object, required: true },
  role: { type: String, required: true },
  activeTab: { type: String, required: true },
  escalatedCount: { type: Number, default: 0 },
  docsCount: { type: Number, default: 0 },
  health: { type: Object, required: true },
});

defineEmits(['update:activeTab', 'switch-role']);

const visibleNavItems = computed(() => {
  const all = [
    { id: 'analytics', icon: BarChart3, label: props.t.nav.analytics, roles: ['admin'] },
    { id: 'inbox', icon: MessageSquareText, label: props.t.nav.inbox, badge: props.escalatedCount, roles: ['admin', 'operator'] },
    { id: 'kb', icon: BookOpen, label: props.t.nav.kb, badge: props.docsCount, muted: true, roles: ['admin', 'operator'] },
    { id: 'ai', icon: Cpu, label: props.t.nav.ai, roles: ['admin', 'operator'] },
    { id: 'billing', icon: CreditCard, label: props.t.nav.billing, roles: ['admin'] },
  ];
  return all.filter((item) => item.roles.includes(props.role));
});
</script>
