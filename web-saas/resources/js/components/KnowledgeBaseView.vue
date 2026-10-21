<template>
  <section class="grid grid-cols-1 xl:grid-cols-[400px_1fr] gap-6">
    <!-- Left Column: Admin Document Ingestion Form OR Operator Read-Only Notice -->
    <div>
      <form
        v-if="role === 'admin'"
        @submit.prevent="submitDoc"
        class="rounded-2xl p-6 pd-card border shadow-sm space-y-4 h-fit xl:sticky xl:top-24"
      >
        <div class="flex items-center gap-2">
          <BookOpen class="w-4 h-4 text-indigo-400" />
          <h3 class="font-semibold pd-text">{{ t.kb.addTitle }}</h3>
        </div>
        <label class="block">
          <span class="text-xs pd-muted">{{ t.kb.docTitleLabel }}</span>
          <input
            v-model="form.title"
            required
            :placeholder="t.kb.docTitlePlaceholder"
            class="w-full mt-1.5 pd-input border pd-border rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-indigo-500"
          />
        </label>

        <div>
          <span class="text-xs pd-muted">{{ t.kb.categoryLabel }}</span>
          <div class="grid grid-cols-2 gap-2 mt-1.5">
            <button
              v-for="c in ['faq', 'return_policy', 'shipping', 'catalog']"
              :key="c"
              type="button"
              @click="form.category = c"
              class="px-3 py-2 rounded-xl text-xs font-semibold border transition cursor-pointer"
              :class="form.category === c ? 'bg-indigo-600 text-white border-indigo-600' : 'pd-input pd-border pd-muted hover:border-indigo-500/40'"
            >
              {{ t.kb.categories[c] || c }}
            </button>
          </div>
        </div>

        <label class="block">
          <span class="text-xs pd-muted">{{ t.kb.contentLabel }}</span>
          <textarea
            v-model="form.content"
            rows="7"
            required
            :placeholder="t.kb.contentPlaceholder"
            class="w-full mt-1.5 pd-input border pd-border rounded-xl px-3.5 py-2.5 text-sm font-mono focus:outline-none focus:border-indigo-500"
          ></textarea>
        </label>

        <div class="flex items-center justify-between text-xs px-3.5 py-2.5 rounded-xl pd-input border pd-border">
          <span class="pd-muted">{{ t.kb.estimatedChunks }}</span>
          <span class="font-bold text-indigo-500 font-mono">{{ estimatedChunks }}</span>
        </div>

        <button
          type="submit"
          class="w-full py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-indigo-600 to-violet-600 text-white hover:from-indigo-500 hover:to-violet-500 cursor-pointer flex items-center justify-center gap-2"
        >
          <Plus class="w-4 h-4" />
          <span>{{ t.kb.indexBtn }}</span>
        </button>
      </form>

      <div v-else class="rounded-2xl p-6 pd-card border shadow-sm space-y-3">
        <Headset class="w-8 h-8 text-emerald-400" />
        <h3 class="font-semibold pd-text">{{ t.roles.operatorTitle }}</h3>
        <p class="text-xs pd-muted leading-relaxed">{{ t.kb.readOnlyNotice }}</p>
      </div>
    </div>

    <!-- Right Column: Search, Custom Pill Category Filter (No <select>!) & Document Cards -->
    <div class="space-y-4">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <Search class="w-4 h-4 pd-muted absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            :placeholder="t.kb.searchDocs"
            class="w-full pd-card border pd-border rounded-xl pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-indigo-500"
          />
        </div>
        <!-- Custom Category Filter Pills (No native <select>) -->
        <div class="flex flex-wrap gap-1.5 p-1 rounded-xl pd-card border pd-border">
          <button
            v-for="cat in ['all', 'faq', 'return_policy', 'shipping', 'catalog']"
            :key="cat"
            type="button"
            @click="categoryFilter = cat"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
            :class="categoryFilter === cat ? 'bg-indigo-600 text-white' : 'pd-muted hover:opacity-80'"
          >
            {{ t.kb.categories[cat] || cat }}
          </button>
        </div>
      </div>

      <div v-if="!filteredDocs.length" class="text-center text-sm pd-muted py-16 rounded-2xl border border-dashed pd-border">
        {{ t.common.noDocs }}
      </div>

      <article
        v-for="doc in filteredDocs"
        :key="doc.id"
        class="rounded-2xl p-5 pd-card border shadow-sm hover:border-indigo-500/40 transition"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h4 class="font-semibold pd-text flex items-center gap-2">
              <FileText class="w-4 h-4 text-indigo-400 shrink-0" />
              <span>{{ doc.title }}</span>
            </h4>
            <div class="flex flex-wrap items-center gap-2 mt-1.5 text-xs">
              <span class="px-2.5 py-0.5 rounded-md bg-indigo-500/15 text-indigo-500 font-medium">
                {{ t.kb.categories[doc.category] || doc.category }}
              </span>
              <span class="px-2.5 py-0.5 rounded-md bg-cyan-500/15 text-cyan-500 font-mono">
                {{ doc.chunks_count }} {{ t.common.chunks }}
              </span>
              <span class="px-2.5 py-0.5 rounded-md bg-emerald-500/15 text-emerald-500 font-medium">
                {{ doc.status }}
              </span>
            </div>
          </div>

          <div v-if="role === 'admin'" class="flex gap-2 shrink-0">
            <button
              type="button"
              @click="$emit('reindex-doc', doc.id)"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold pd-input border pd-border pd-text hover:border-indigo-500/50 cursor-pointer flex items-center gap-1.5"
            >
              <RefreshCw class="w-3.5 h-3.5" />
              <span>{{ t.kb.reindexBtn }}</span>
            </button>
            <button
              type="button"
              @click="$emit('delete-doc', doc.id)"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/15 text-rose-500 hover:bg-rose-500/25 cursor-pointer flex items-center gap-1.5"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>{{ t.kb.deleteBtn }}</span>
            </button>
          </div>
        </div>
        <p class="text-sm pd-muted mt-3 whitespace-pre-line leading-relaxed">{{ doc.content }}</p>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import {
  BookOpen,
  Plus,
  Headset,
  Search,
  FileText,
  RefreshCw,
  Trash2,
} from 'lucide-vue-next';

const props = defineProps({
  t: { type: Object, required: true },
  role: { type: String, required: true },
  documents: { type: Array, required: true },
});

const emit = defineEmits(['add-doc', 'reindex-doc', 'delete-doc']);

const searchQuery = ref('');
const categoryFilter = ref('all');
const form = reactive({ title: '', category: 'faq', content: '' });

const estimatedChunks = computed(() => {
  const len = (form.content || '').trim().length;
  return len ? Math.max(1, Math.ceil(len / 420)) : 0;
});

const filteredDocs = computed(() => {
  return props.documents.filter((doc) => {
    if (categoryFilter.value !== 'all' && doc.category !== categoryFilter.value) return false;
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return true;
    return doc.title.toLowerCase().includes(q) || doc.content.toLowerCase().includes(q);
  });
});

function submitDoc() {
  emit('add-doc', { ...form });
  form.title = '';
  form.content = '';
}
</script>
