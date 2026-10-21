<template>
  <div class="min-h-screen flex pd-bg pd-text transition-colors duration-200">
    <!-- Toast Notification (Zero emojis, Lucide icons) -->
    <div
      v-if="toast"
      class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl border shadow-2xl text-sm font-medium"
      :class="toast.type === 'error'
        ? 'bg-rose-950/95 border-rose-500/40 text-rose-200'
        : 'bg-emerald-950/95 border-emerald-500/40 text-emerald-200'"
    >
      <AlertTriangle v-if="toast.type === 'error'" class="w-4 h-4 text-rose-400 shrink-0" />
      <CheckCircle2 v-else class="w-4 h-4 text-emerald-400 shrink-0" />
      <span>{{ toast.message }}</span>
    </div>

    <!-- Left Sidebar (Role Switcher + Navigation + Service Health) -->
    <SidebarNav
      :t="t"
      :role="role"
      v-model:activeTab="activeTab"
      :escalated-count="escalatedCount"
      :docs-count="documents.length"
      :health="health"
      @switch-role="switchRole"
    />

    <!-- Main Workspace Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <TopBar
        :t="t"
        :tenant="tenant"
        :tenants-list="tenantsList"
        :role="role"
        :locale="locale"
        :theme="theme"
        v-model:activeTab="activeTab"
        @set-locale="setLocale"
        @toggle-theme="toggleTheme"
        @switch-tenant="switchTenant"
      />

      <main class="flex-1 p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
        <AnalyticsView
          v-if="activeTab === 'analytics'"
          :t="t"
          :tenant="tenant"
          :metrics="metrics"
          :intent-distribution="intentDistribution"
          :quota-percent="quotaPercent"
          @open-escalated="openEscalatedInbox"
        />

        <InboxView
          v-if="activeTab === 'inbox'"
          :t="t"
          :tickets="filteredTickets"
          :selected-ticket="selectedTicket"
          v-model:inboxFilter="inboxFilter"
          v-model:inboxSearch="inboxSearch"
          @select-ticket="selectedTicket = $event"
          @send-reply="sendReply"
          @change-status="changeTicketStatus"
        />

        <KnowledgeBaseView
          v-if="activeTab === 'kb'"
          :t="t"
          :role="role"
          :documents="documents"
          @add-doc="addDocument"
          @reindex-doc="reindexDocument"
          @delete-doc="deleteDocument"
        />

        <AiProvidersView
          v-if="activeTab === 'ai'"
          :t="t"
          :role="role"
          :tenant="tenant"
          :supported-providers="supportedProviders"
          :sandbox-result="sandboxResult"
          :sandbox-loading="sandboxLoading"
          @save-ai="saveAiSettings"
          @run-sandbox="runSandbox"
        />

        <BillingView
          v-if="activeTab === 'billing'"
          :t="t"
          :tenant="tenant"
          :plans="plans"
          @upgrade-plan="upgradePlan"
        />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { I18N as translations } from './i18n';
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import SidebarNav from './components/SidebarNav.vue';
import TopBar from './components/TopBar.vue';
import AnalyticsView from './components/AnalyticsView.vue';
import InboxView from './components/InboxView.vue';
import KnowledgeBaseView from './components/KnowledgeBaseView.vue';
import AiProvidersView from './components/AiProvidersView.vue';
import BillingView from './components/BillingView.vue';

const initialState = window.__PULSEDESK_STATE__ || {};
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const savedLocale = localStorage.getItem('pd_locale');
const locale = ref(savedLocale === 'en' ? 'en' : 'ru');
const theme = ref(localStorage.getItem('pd_theme') === 'light' ? 'light' : 'dark');
const role = ref(initialState.current_role || 'admin');

// Persistent Navigation Tab via Hash & LocalStorage
const validTabs = ['analytics', 'inbox', 'kb', 'ai', 'billing'];
function getInitialTab() {
  const hash = window.location.hash.replace(/^#/, '');
  if (validTabs.includes(hash)) {
    if (role.value === 'operator' && (hash === 'analytics' || hash === 'billing')) {
      return 'inbox';
    }
    return hash;
  }
  const stored = localStorage.getItem('pd_active_tab');
  if (stored && validTabs.includes(stored)) {
    if (role.value === 'operator' && (stored === 'analytics' || stored === 'billing')) {
      return 'inbox';
    }
    return stored;
  }
  return role.value === 'operator' ? 'inbox' : 'analytics';
}

const activeTab = ref(getInitialTab());

// Sync activeTab changes to URL hash and localStorage
watch(activeTab, (newTab) => {
  if (validTabs.includes(newTab)) {
    localStorage.setItem('pd_active_tab', newTab);
    if (window.location.hash !== `#${newTab}`) {
      window.history.replaceState(null, '', `#${newTab}`);
    }
  }
});

function handleHashChange() {
  const hash = window.location.hash.replace(/^#/, '');
  if (validTabs.includes(hash)) {
    if (role.value === 'operator' && (hash === 'analytics' || hash === 'billing')) {
      activeTab.value = 'inbox';
    } else {
      activeTab.value = hash;
    }
  }
}

const tenant = ref(initialState.tenant || { plan: 'growth', used_quota: 0, monthly_quota: 2500 });
const tenantsList = ref(initialState.tenants_list || []);
const metrics = ref(initialState.metrics || {});
const documents = ref(initialState.documents || []);
const tickets = ref(initialState.tickets || []);
const supportedProviders = ref(initialState.supported_providers || {});
const plans = ref(initialState.plans || {});
const health = ref({ web_saas: 'online', rag_api: 'standby', tg_bot: 'standby' });

const inboxFilter = ref('all');
const inboxSearch = ref('');
const selectedTicket = ref(tickets.value[0] || null);
const sandboxResult = ref(null);
const sandboxLoading = ref(false);
const toast = ref(null);
let pollTimer = null;

const t = computed(() => translations[locale.value] || translations.ru);
const escalatedCount = computed(() => tickets.value.filter((item) => item.status === 'escalated').length);
const quotaPercent = computed(() => {
  const max = Math.max(1, tenant.value.monthly_quota || 2500);
  return Math.min(100, Math.round(((tenant.value.used_quota || 0) / max) * 100));
});

const intentDistribution = computed(() => {
  const counts = {};
  tickets.value.forEach((tk) => {
    const key = tk.intent || 'general_support';
    counts[key] = (counts[key] || 0) + 1;
  });
  const total = Math.max(1, tickets.value.length);
  const palette = {
    return_policy: 'bg-indigo-500',
    return_refund: 'bg-indigo-500',
    shipping: 'bg-cyan-500',
    order_status: 'bg-cyan-500',
    product_info: 'bg-emerald-500',
    human_request: 'bg-amber-500',
    security_guardrail: 'bg-rose-500',
    general_support: 'bg-violet-500',
  };
  return Object.entries(counts).map(([id, count]) => ({
    id,
    label: t.value.intents?.[id] || id,
    count,
    pct: Math.round((count / total) * 100),
    color: palette[id] || 'bg-indigo-500',
  }));
});

const filteredTickets = computed(() => {
  return tickets.value.filter((tk) => {
    if (inboxFilter.value === 'escalated' && tk.status !== 'escalated') return false;
    if (inboxFilter.value === 'resolved' && tk.status === 'escalated') return false;
    const q = inboxSearch.value.trim().toLowerCase();
    if (!q) return true;
    return (
      (tk.customer_name || '').toLowerCase().includes(q) ||
      (tk.intent || '').toLowerCase().includes(q)
    );
  });
});

function applyThemeClass() {
  document.documentElement.classList.toggle('dark', theme.value === 'dark');
}

function setLocale(lang) {
  if (lang !== 'ru' && lang !== 'en') return;
  locale.value = lang;
  localStorage.setItem('pd_locale', lang);
}

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('pd_theme', theme.value);
  applyThemeClass();
}

function openEscalatedInbox() {
  inboxFilter.value = 'escalated';
  activeTab.value = 'inbox';
}

function notify(message, type = 'success') {
  toast.value = { message, type };
  setTimeout(() => {
    toast.value = null;
  }, 3600);
}

async function apiRequest(url, method = 'POST', payload = null) {
  try {
    const opts = {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
    };
    if (payload) opts.body = JSON.stringify(payload);
    const res = await fetch(url, opts);
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Request failed');
    return data;
  } catch (err) {
    notify(err.message, 'error');
    throw err;
  }
}

async function refreshDashboard() {
  const res = await fetch('/dashboard', { headers: { Accept: 'application/json' } });
  if (!res.ok) return;
  const data = await res.json();
  role.value = data.current_role || role.value;
  tenantsList.value = data.tenants_list || tenantsList.value;
  tenant.value = data.tenant;
  metrics.value = data.metrics;
  documents.value = data.documents;
  tickets.value = data.tickets;
  if (selectedTicket.value) {
    selectedTicket.value = tickets.value.find((tk) => tk.id === selectedTicket.value.id) || tickets.value[0] || null;
  } else {
    selectedTicket.value = tickets.value[0] || null;
  }
}

async function checkServicesHealth() {
  try {
    const res = await fetch('/system/health', { headers: { Accept: 'application/json' } });
    if (res.ok) {
      health.value = await res.json();
    }
  } catch {
    // keep current health indicator
  }
}

async function switchRole(newRole) {
  await apiRequest('/workspace/role', 'POST', { role: newRole });
  role.value = newRole;
  if (newRole === 'operator' && (activeTab.value === 'analytics' || activeTab.value === 'billing')) {
    activeTab.value = 'inbox';
  }
  await refreshDashboard();
  notify(t.value.toasts.roleSwitched);
}

async function switchTenant(tenantSlug) {
  await apiRequest('/workspace/tenant', 'POST', { tenant_slug: tenantSlug });
  await refreshDashboard();
  notify(`${t.value.toasts.tenantSwitched}: ${tenant.value.name}`);
}

async function addDocument(form) {
  await apiRequest('/documents', 'POST', form);
  await refreshDashboard();
  notify(t.value.toasts.docIndexed);
}

async function reindexDocument(docId) {
  await apiRequest(`/documents/${docId}/reindex`, 'POST');
  await refreshDashboard();
  notify(t.value.toasts.docReindexed);
}

async function deleteDocument(docId) {
  await apiRequest(`/documents/${docId}`, 'DELETE');
  await refreshDashboard();
  notify(t.value.toasts.docDeleted);
}

async function saveAiSettings(form) {
  await apiRequest('/settings/ai', 'POST', form);
  await refreshDashboard();
  notify(t.value.toasts.aiSaved);
}

async function runSandbox(query) {
  sandboxLoading.value = true;
  try {
    sandboxResult.value = await apiRequest('/sandbox/query', 'POST', { query });
    await refreshDashboard();
  } finally {
    sandboxLoading.value = false;
  }
}

async function sendReply(message) {
  if (!selectedTicket.value) return;
  await apiRequest(`/tickets/${selectedTicket.value.id}/reply`, 'POST', {
    operator_name: role.value === 'admin' ? 'Admin Supervisor' : 'Support Operator',
    message,
  });
  await refreshDashboard();
  notify(t.value.toasts.replySent);
}

async function changeTicketStatus({ id, status }) {
  await apiRequest(`/tickets/${id}/status`, 'POST', { status });
  await refreshDashboard();
  notify(t.value.toasts.statusChanged);
}

async function upgradePlan(planId) {
  await apiRequest('/billing/checkout', 'POST', { plan: planId });
  await refreshDashboard();
  notify(t.value.toasts.planUpgraded);
}

onMounted(() => {
  applyThemeClass();
  checkServicesHealth();
  window.addEventListener('hashchange', handleHashChange);
  pollTimer = setInterval(() => {
    if (activeTab.value === 'inbox' || role.value === 'operator') {
      refreshDashboard();
    }
  }, 6000);
});

onUnmounted(() => {
  window.removeEventListener('hashchange', handleHashChange);
  if (pollTimer) clearInterval(pollTimer);
});
</script>
