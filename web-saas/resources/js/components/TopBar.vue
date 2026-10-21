<template>
  <header class="sticky top-0 z-20 border-b pd-border pd-sidebar/90 backdrop-blur px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
    <!-- Left: Custom Tenant Switcher Popover (No native <select>) & Workspace Role Badge -->
    <div class="flex items-center gap-3 flex-wrap">
      <div class="relative">
        <button
          type="button"
          @click="tenantMenuOpen = !tenantMenuOpen"
          class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl pd-input border pd-border text-sm font-semibold cursor-pointer hover:border-indigo-500/60 transition"
        >
          <Store class="w-4 h-4 text-indigo-500 shrink-0" />
          <span>{{ tenant.name }}</span>
          <span class="text-xs pd-muted font-mono">({{ tenant.slug }})</span>
          <ChevronDown class="w-3.5 h-3.5 pd-muted shrink-0" />
        </button>

        <div
          v-if="tenantMenuOpen"
          class="absolute left-0 mt-2 w-72 rounded-2xl pd-card border pd-border shadow-2xl p-2 z-40 space-y-1"
        >
          <button
            v-for="item in tenantsList"
            :key="item.slug"
            type="button"
            @click="selectTenant(item.slug)"
            class="w-full text-left px-3 py-2.5 rounded-xl text-sm flex items-center justify-between transition cursor-pointer"
            :class="item.slug === tenant.slug ? 'bg-indigo-600 text-white' : 'hover:bg-indigo-500/10 pd-text'"
          >
            <div>
              <div class="font-semibold">{{ item.name }}</div>
              <div class="text-xs opacity-75 font-mono">{{ item.slug }} • {{ item.ai_provider }}</div>
            </div>
            <span class="text-xs uppercase px-2 py-0.5 rounded-md bg-black/20 font-semibold">{{ item.plan }}</span>
          </button>
        </div>
      </div>

      <span
        class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 border"
        :class="role === 'admin' ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'"
      >
        <ShieldCheck v-if="role === 'admin'" class="w-3.5 h-3.5 shrink-0" />
        <Headset v-else class="w-3.5 h-3.5 shrink-0" />
        <span>{{ role === 'admin' ? t.roles.adminTitle : t.roles.operatorTitle }}</span>
      </span>
    </div>

    <!-- Mobile Custom Pill Nav (No native <select>) -->
    <div class="flex md:hidden flex-wrap gap-1.5 w-full">
      <button
        v-for="tab in ['analytics', 'inbox', 'kb', 'ai', 'billing']"
        :key="tab"
        type="button"
        @click="$emit('update:activeTab', tab)"
        class="px-3 py-1.5 rounded-lg text-xs font-semibold"
        :class="activeTab === tab ? 'bg-indigo-600 text-white' : 'pd-input border pd-border pd-muted'"
      >
        {{ t.nav[tab] }}
      </button>
    </div>

    <!-- Right: Active LLM Badge, Theme Switcher (Light/Dark), Language Switcher (RU/EN) -->
    <div class="flex items-center gap-2.5">
      <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-xl pd-input border pd-border text-xs">
        <Sparkles class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
        <span class="pd-muted">LLM:</span>
        <span class="font-semibold text-indigo-500 uppercase">{{ tenant.ai_provider }}</span>
        <span class="pd-muted">•</span>
        <span class="font-mono pd-text">{{ tenant.ai_model }}</span>
      </div>

      <!-- Light / Dark Theme Switcher -->
      <button
        type="button"
        @click="$emit('toggle-theme')"
        class="px-3 py-1.5 rounded-xl pd-input border pd-border text-xs font-semibold flex items-center gap-1.5 cursor-pointer hover:border-indigo-500/50 transition"
      >
        <Moon v-if="theme === 'dark'" class="w-3.5 h-3.5 text-indigo-400 shrink-0" />
        <Sun v-else class="w-3.5 h-3.5 text-amber-500 shrink-0" />
        <span>{{ theme === 'dark' ? t.theme.dark : t.theme.light }}</span>
      </button>

      <!-- Strictly RU / EN Language Switcher -->
      <div class="flex items-center p-1 rounded-xl pd-input border pd-border" role="group" aria-label="Language">
        <button
          v-for="lang in ['ru', 'en']"
          :key="lang"
          type="button"
          @click="$emit('set-locale', lang)"
          class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase transition cursor-pointer"
          :class="locale === lang ? 'bg-indigo-600 text-white' : 'pd-muted hover:opacity-80'"
        >
          {{ lang }}
        </button>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref } from 'vue';
import { Store, ChevronDown, ShieldCheck, Headset, Sparkles, Sun, Moon } from 'lucide-vue-next';

defineProps({
  t: { type: Object, required: true },
  tenant: { type: Object, required: true },
  tenantsList: { type: Array, default: () => [] },
  role: { type: String, required: true },
  locale: { type: String, required: true },
  theme: { type: String, required: true },
  activeTab: { type: String, required: true },
});

const emit = defineEmits(['set-locale', 'toggle-theme', 'switch-tenant', 'update:activeTab']);
const tenantMenuOpen = ref(false);

function selectTenant(slug) {
  tenantMenuOpen.value = false;
  emit('switch-tenant', slug);
}
</script>
