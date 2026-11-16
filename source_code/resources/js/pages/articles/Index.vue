<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { relativeTime, wordLabel } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Copy, FileText, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { route } from 'ziggy-js';

interface ArticleRow {
    id: number;
    title: string;
    slug: string;
    status: string;
    topic_id: number | null;
    topic: string | null;
    words: number;
    updated_at: string | null;
    editor: string | null;
    published_at: string | null;
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string };
    articles: {
        data: ArticleRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    topics: { id: number; title: string; parent_id: number | null }[];
    filters: { q: string; status: string; topic: string };
    counts: Record<string, number>;
    canWrite: boolean;
}>();

const createOpen = ref(false);
const deleteTarget = ref<ArticleRow | null>(null);
const filters = reactive({ q: props.filters.q, status: props.filters.status, topic: props.filters.topic });

const form = useForm({
    title: '',
    topic_id: null as number | null,
});

const statusTabs = [
    { key: '', label: 'All', count: 'all' },
    { key: 'draft', label: 'Drafts', count: 'draft' },
    { key: 'in_review', label: 'In review', count: 'in_review' },
    { key: 'published', label: 'Published', count: 'published' },
    { key: 'archived', label: 'Archived', count: 'archived' },
];

function applyFilters() {
    router.get(
        route('author.articles.index', props.encyclopedia.slug),
        {
            ...(filters.q ? { q: filters.q } : {}),
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.topic ? { topic: filters.topic } : {}),
        },
        { preserveState: true, replace: true },
    );
}

function setStatus(status: string) {
    filters.status = status;
    applyFilters();
}

function createArticle() {
    form.post(route('author.articles.store', props.encyclopedia.slug), {
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
        },
    });
}

function duplicate(article: ArticleRow) {
    router.post(route('author.articles.duplicate', [props.encyclopedia.slug, article.id]));
}

function destroy() {
    if (!deleteTarget.value) return;
    router.delete(route('author.articles.destroy', [props.encyclopedia.slug, deleteTarget.value.id]), {
        preserveScroll: true,
        onFinish: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head :title="`Articles · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="articles" :breadcrumbs="[{ label: 'Articles' }]">
        <PageHeader eyebrow="Content" title="Articles" description="Every entry in this encyclopedia, from first draft to published revision.">
            <template #actions>
                <button v-if="canWrite" class="btn-primary" type="button" @click="createOpen = true"><Plus class="size-4" /> New article</button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        filters.status === tab.key
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                    "
                    @click="setStatus(tab.key)"
                >
                    {{ tab.label }}
                    <span class="ml-1 tabular-nums opacity-60">{{ props.counts[tab.count] ?? 0 }}</span>
                </button>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <select v-model="filters.topic" class="input sm:w-48" aria-label="Filter by topic" @change="applyFilters">
                    <option value="">All topics</option>
                    <option v-for="topic in props.topics" :key="topic.id" :value="String(topic.id)">{{ topic.title }}</option>
                </select>
                <form class="relative" @submit.prevent="applyFilters">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="filters.q" class="input pl-9 sm:w-56" placeholder="Search articles…" aria-label="Search articles" />
                </form>
            </div>
        </div>

        <div v-if="articles.data.length" class="surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-secondary/50 text-[10px] uppercase tracking-[0.12em] text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Article</th>
                            <th class="hidden px-4 py-2.5 font-semibold md:table-cell">Topic</th>
                            <th class="px-4 py-2.5 font-semibold">Status</th>
                            <th class="hidden px-4 py-2.5 text-right font-semibold sm:table-cell">Words</th>
                            <th class="hidden px-4 py-2.5 font-semibold lg:table-cell">Updated</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="article in articles.data" :key="article.id" class="group transition-colors hover:bg-secondary/40">
                            <td class="px-4 py-3">
                                <Link :href="route('author.articles.edit', [encyclopedia.slug, article.id])" class="flex items-center gap-2.5">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded border border-border bg-card"
                                        ><FileText class="size-3.5 text-muted-foreground"
                                    /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium">{{ article.title }}</span>
                                        <span class="block truncate font-mono text-[10px] text-muted-foreground">/{{ article.slug }}</span>
                                    </span>
                                </Link>
                            </td>
                            <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">{{ article.topic ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3"><StatusBadge :value="article.status" /></td>
                            <td class="hidden px-4 py-3 text-right text-xs tabular-nums text-muted-foreground sm:table-cell">
                                {{ wordLabel(article.words) }}
                            </td>
                            <td class="hidden px-4 py-3 text-xs text-muted-foreground lg:table-cell">{{ relativeTime(article.updated_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn-ghost btn-sm" type="button" title="Duplicate" @click="duplicate(article)">
                                        <Copy class="size-3.5" />
                                    </button>
                                    <button class="btn-ghost btn-sm text-destructive" type="button" title="Delete" @click="deleteTarget = article">
                                        <Trash2 class="size-3.5" />
                                    </button>
                                    <Link :href="route('author.articles.edit', [encyclopedia.slug, article.id])" class="btn-secondary btn-sm"
                                        >Edit</Link
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-xs text-muted-foreground">
                <span>Showing {{ articles.from ?? 0 }}–{{ articles.to ?? 0 }} of {{ articles.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in articles.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded px-2 py-1 hover:bg-secondary"
                            :class="link.active ? 'bg-foreground text-background' : ''"
                            preserve-scroll
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span v-else class="rounded px-2 py-1 opacity-40" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>

        <EmptyState v-else title="No articles match this view" description="Adjust the filters or write the first article for this encyclopedia.">
            <button v-if="canWrite" class="btn-primary" type="button" @click="createOpen = true"><Plus class="size-4" /> New article</button>
        </EmptyState>

        <Modal
            v-model:open="createOpen"
            title="New article"
            description="Start with a title and an optional topic. You can refine everything in the editor."
        >
            <form id="create-article-form" class="space-y-4" @submit.prevent="createArticle">
                <div>
                    <label class="label" for="new-title">Title</label>
                    <input id="new-title" v-model="form.title" class="input" placeholder="The Pyramids of Giza" autofocus />
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label class="label" for="new-topic">Primary topic</label>
                    <select id="new-topic" v-model="form.topic_id" class="input">
                        <option :value="null">Unassigned</option>
                        <option v-for="topic in props.topics" :key="topic.id" :value="topic.id">
                            {{ topic.parent_id ? '— ' : '' }}{{ topic.title }}
                        </option>
                    </select>
                    <p v-if="form.errors.topic_id" class="field-error">{{ form.errors.topic_id }}</p>
                </div>
            </form>
            <template #footer>
                <button class="btn-secondary" type="button" @click="createOpen = false">Cancel</button>
                <button class="btn-primary" type="submit" form="create-article-form" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create draft' }}
                </button>
            </template>
        </Modal>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete this article?"
            :description="`“${deleteTarget?.title}” will be removed from the encyclopedia. This action can only be undone by restoring the database.`"
            confirm-label="Delete article"
            tone="danger"
            @update:open="deleteTarget = null"
            @confirm="destroy"
        />
    </AuthorLayout>
</template>
