<template>
  <section class="grid grid-cols-1 lg:grid-cols-[370px_1fr] gap-6 h-[calc(100vh-145px)] min-h-[580px]">
    <!-- Left Column: Ticket Queue -->
    <div class="rounded-2xl pd-card border shadow-sm flex flex-col min-h-0">
      <div class="p-4 border-b pd-border space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider pd-muted flex items-center gap-1.5">
            <MessageSquare class="w-3.5 h-3.5 text-indigo-400" />
            {{ t.nav.inbox }}
          </span>
          <span class="flex items-center gap-1.5 text-xs text-emerald-500 font-medium">
            <span class="h-2 w-2 rounded-full bg-emerald-500 pd-live"></span>{{ t.roles.liveSync }}
          </span>
        </div>
        <div class="relative">
          <Search class="w-4 h-4 pd-muted absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            :value="inboxSearch"
            @input="$emit('update:inboxSearch', $event.target.value)"
            :placeholder="t.inbox.searchPlaceholder"
            class="w-full pd-input border pd-border rounded-xl pl-10 pr-3.5 py-2 text-sm focus:outline-none focus:border-indigo-500"
          />
        </div>
        <!-- Custom Segmented Filter Pills -->
        <div class="flex gap-1 p-1 rounded-xl pd-input border pd-border">
          <button
            v-for="f in [
              { id: 'all', label: t.inbox.filterAll },
              { id: 'escalated', label: t.inbox.filterEscalated },
              { id: 'resolved', label: t.inbox.filterResolved },
            ]"
            :key="f.id"
            type="button"
            @click="$emit('update:inboxFilter', f.id)"
            class="flex-1 px-2 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
            :class="inboxFilter === f.id ? 'bg-indigo-600 text-white' : 'pd-muted hover:opacity-80'"
          >
            {{ f.label }}
          </button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto pd-scroll p-2 space-y-1.5">
        <div v-if="!tickets.length" class="text-center text-sm pd-muted py-12">{{ t.common.noTickets }}</div>
        <button
          v-for="ticket in tickets"
          :key="ticket.id"
          type="button"
          @click="$emit('select-ticket', ticket)"
          class="w-full text-left p-3.5 rounded-xl flex gap-3 transition border cursor-pointer"
          :class="selectedTicket && selectedTicket.id === ticket.id ? 'bg-indigo-600/15 border-indigo-500' : 'border-transparent hover:bg-indigo-500/5'"
        >
          <div
            class="h-10 w-10 shrink-0 rounded-full flex items-center justify-center text-sm font-bold"
            :class="ticket.status === 'escalated' ? 'bg-amber-500/20 text-amber-500' : 'bg-indigo-500/20 text-indigo-500'"
          >
            {{ getInitials(ticket.customer_name) }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-sm pd-text truncate">{{ ticket.customer_name }}</span>
              <span v-if="ticket.status === 'escalated'" class="h-2.5 w-2.5 rounded-full bg-amber-500 pd-live shrink-0"></span>
            </div>
            <div class="text-xs pd-muted truncate mt-0.5">
              {{ ticket.messages && ticket.messages.length ? ticket.messages[ticket.messages.length - 1].content : '' }}
            </div>
            <div class="flex items-center gap-2 mt-2">
              <span
                class="text-xs px-2 py-0.5 rounded-md font-medium"
                :class="ticket.status === 'escalated' ? 'bg-amber-500/15 text-amber-500' : (ticket.was_deflected ? 'bg-emerald-500/15 text-emerald-500' : 'bg-sky-500/15 text-sky-500')"
              >
                {{ ticketStatusLabel(ticket) }}
              </span>
              <span class="text-xs pd-muted font-mono">{{ Math.round(ticket.avg_confidence * 100) }}%</span>
            </div>
          </div>
        </button>
      </div>
    </div>

    <!-- Right Column: Active Conversation & Operator Bridge -->
    <div v-if="selectedTicket" class="rounded-2xl pd-card border shadow-sm flex flex-col min-h-0">
      <div class="px-6 py-4 border-b pd-border flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="h-11 w-11 rounded-full bg-gradient-to-br from-indigo-500 to-cyan-400 text-white flex items-center justify-center font-bold">
            {{ getInitials(selectedTicket.customer_name) }}
          </div>
          <div class="min-w-0">
            <div class="font-bold pd-text truncate">{{ selectedTicket.customer_name }}</div>
            <div class="text-xs pd-muted">
              @{{ selectedTicket.customer_username || 'telegram_buyer' }} · Chat ID {{ selectedTicket.telegram_chat_id }} · {{ t.common.intent }}:
              <span class="font-semibold pd-text">{{ selectedTicket.intent }}</span>
            </div>
          </div>
        </div>
        <div class="flex gap-2">
          <button
            v-if="selectedTicket.status !== 'escalated'"
            type="button"
            @click="$emit('change-status', { id: selectedTicket.id, status: 'escalated' })"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-500/15 text-amber-500 hover:bg-amber-500/25 cursor-pointer flex items-center gap-1.5"
          >
            <AlertCircle class="w-3.5 h-3.5" />
            {{ t.inbox.escalateBtn }}
          </button>
          <button
            v-if="selectedTicket.status === 'escalated'"
            type="button"
            @click="$emit('change-status', { id: selectedTicket.id, status: 'ai_handling' })"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-500/15 text-indigo-500 hover:bg-indigo-500/25 cursor-pointer flex items-center gap-1.5"
          >
            <Bot class="w-3.5 h-3.5" />
            {{ t.inbox.returnToAi }}
          </button>
          <button
            v-if="selectedTicket.status !== 'resolved'"
            type="button"
            @click="$emit('change-status', { id: selectedTicket.id, status: 'resolved' })"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/15 text-emerald-500 hover:bg-emerald-500/25 cursor-pointer flex items-center gap-1.5"
          >
            <CheckCircle2 class="w-3.5 h-3.5" />
            {{ t.inbox.resolveBtn }}
          </button>
        </div>
      </div>

      <div v-if="selectedTicket.escalation_reason" class="mx-6 mt-4 px-4 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-500 font-medium flex items-center gap-2">
        <AlertCircle class="w-4 h-4 shrink-0" />
        <span>{{ selectedTicket.escalation_reason }}</span>
      </div>

      <div class="flex-1 overflow-y-auto pd-scroll px-6 py-5 space-y-4">
        <div
          v-for="msg in selectedTicket.messages"
          :key="msg.id"
          class="flex"
          :class="msg.sender_type === 'customer' ? 'justify-start' : 'justify-end'"
        >
          <div
            class="max-w-[78%] rounded-2xl px-4 py-3 text-sm border"
            :class="msg.sender_type === 'customer' ? 'pd-input pd-border rounded-tl-sm' : (msg.sender_type === 'operator' ? 'bg-emerald-500/10 border-emerald-500/30 rounded-tr-sm' : 'bg-indigo-500/10 border-indigo-500/30 rounded-tr-sm')"
          >
            <div class="flex items-center justify-between gap-4 mb-1 text-xs">
              <span class="font-semibold flex items-center gap-1.5" :class="msg.sender_type === 'customer' ? 'pd-muted' : (msg.sender_type === 'operator' ? 'text-emerald-500' : 'text-indigo-500')">
                <Bot v-if="msg.sender_type === 'ai'" class="w-3.5 h-3.5" />
                <Headset v-else-if="msg.sender_type === 'operator'" class="w-3.5 h-3.5" />
                <User v-else class="w-3.5 h-3.5" />
                {{ msg.sender_name }}
              </span>
              <span v-if="msg.confidence_score" class="pd-muted font-mono">{{ Math.round(msg.confidence_score * 100) }}%</span>
            </div>
            <div class="whitespace-pre-line leading-relaxed pd-text">{{ msg.content }}</div>
            <div v-if="msg.sources && msg.sources.length" class="mt-2 pt-2 border-t pd-border text-xs pd-muted flex items-center gap-1.5 flex-wrap">
              <BookOpen class="w-3.5 h-3.5 text-indigo-400" />
              <span>{{ t.inbox.sourcesCited }}</span>
              <span v-for="(s, i) in msg.sources" :key="i" class="font-medium pd-text">
                {{ s.title }}<span v-if="i < msg.sources.length - 1">, </span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- AI Copilot Quick Replies & Operator Send Form -->
      <div class="p-4 border-t pd-border space-y-3">
        <div class="text-xs pd-muted font-medium flex items-center gap-1.5">
          <Sparkles class="w-3.5 h-3.5 text-indigo-400" />
          {{ t.inbox.copilotLabel }}
        </div>
        <div class="flex gap-2 overflow-x-auto pd-scroll pb-1">
          <button
            v-for="(qr, i) in t.inbox.quickReplies"
            :key="i"
            type="button"
            @click="replyDraft = qr"
            class="shrink-0 max-w-[300px] truncate text-xs px-3 py-1.5 rounded-lg pd-input border pd-border pd-text hover:border-indigo-500/50 cursor-pointer"
          >
            {{ qr }}
          </button>
        </div>
        <form @submit.prevent="submitReply" class="flex gap-2">
          <input
            v-model="replyDraft"
            :placeholder="t.inbox.replyPlaceholder"
            class="flex-1 pd-input border pd-border rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-indigo-500"
          />
          <button
            type="submit"
            :disabled="!replyDraft.trim()"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-40 cursor-pointer flex items-center gap-2"
          >
            <Send class="w-4 h-4" />
            <span>{{ t.inbox.sendBtn }}</span>
          </button>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import {
  MessageSquare,
  Search,
  AlertCircle,
  Bot,
  CheckCircle2,
  Headset,
  User,
  BookOpen,
  Sparkles,
  Send,
} from 'lucide-vue-next';

const props = defineProps({
  t: { type: Object, required: true },
  tickets: { type: Array, required: true },
  selectedTicket: { type: Object, default: null },
  inboxFilter: { type: String, required: true },
  inboxSearch: { type: String, required: true },
});

const emit = defineEmits([
  'update:inboxFilter',
  'update:inboxSearch',
  'select-ticket',
  'send-reply',
  'change-status',
]);

const replyDraft = ref('');

function getInitials(name) {
  return (name || 'U')
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
}

function ticketStatusLabel(ticket) {
  if (ticket.status === 'escalated') return props.t.inbox.needsHuman;
  if (ticket.was_deflected) return props.t.inbox.resolvedByAi;
  return props.t.inbox.resolvedByHuman;
}

function submitReply() {
  if (!replyDraft.value.trim()) return;
  emit('send-reply', replyDraft.value);
  replyDraft.value = '';
}
</script>
