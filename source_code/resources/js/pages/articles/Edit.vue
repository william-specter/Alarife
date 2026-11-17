<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useEncyclopediaRealtime } from '@/composables/useRealtime';
import EditorToolbar from '@/editor/EditorToolbar.vue';
import MediaPicker from '@/editor/MediaPicker.vue';
import TiptapEditor from '@/editor/TiptapEditor.vue';
import { formatDateTime, relativeTime } from '@/lib/format';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Check, Clock3, ExternalLink, History, Image as ImageIcon, Loader2, Rocket, Save, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface MediaItem {
    id: number;
    url: string;
    filename?: string;
    alt_text?: string | null;
    caption?: string | null;
    width?: number | null;
    height?: number | null;
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string };
    article: {
        id: number;
        title: string;
        slug: string;
        excerpt: string | null;
        content: Record<string, unknown> | null;
        status: string;
        topic_id: number | null;
        featured_image_id: number | null;
        revision_number: number;
        updated_at: string | null;
        published_at: string | null;
        words: number;
        reading_minutes: number;
    };
    topics: { id: number; title: string; parent_id: number | null }[];
    media: MediaItem[];
    revisions: { id: number; revision_number: number; title: string; word_count: number; editor: string | null; created_at: string | null }[];
    urls: { update: string; autosave: string; status: string; preview: string; index: string };
    canPublish: boolean;
    canDelete: boolean;
}>();

const editorRef = ref<InstanceType<typeof TiptapEditor> | null>(null);
const editorInstance = ref<any>(null);
const authUser = usePage<SharedData>().props.auth.user;
const remoteNotice = ref<string | null>(null);

useEncyclopediaRealtime(props.encyclopedia.id, (event) => {
    const articleEvents = ['article.saved', 'article.status', 'article.restored', 'article.deleted'];
    if (!articleEvents.includes(event.type)) return;
    if (Number(event.payload.article_id) !== props.article.id) return;
    if (Number(event.payload.editor_id) === Number(authUser.id)) return;

    remoteNotice.value =
        event.type === 'article.deleted'
            ? `${event.payload.editor ?? 'Another editor'} deleted this article.`
            : `${event.payload.editor ?? 'Another editor'} changed this article (revision ${event.payload.revision ?? '—'}).`;
});
const title = ref(props.article.title);
const slug = ref(props.article.slug);
const excerpt = ref(props.article.excerpt ?? '');
const content = ref<Record<string, unknown> | null>(props.article.content);
const topicId = ref<number | null>(props.article.topic_id);
const featuredId = ref<number | null>(props.article.featured_image_id);
const revision = ref(props.article.revision_number);
const words = ref(props.article.words);

const saveState = ref<'saved' | 'dirty' | 'saving' | 'conflict' | 'error'>('saved');
const savedAt = ref<string | null>(props.article.updated_at);
const conflictMessage = ref('');
const errorMessage = ref('');
const tab = ref<'details' | 'history'>('details');
const picker = ref<'image' | 'gallery' | null>(null);
const deleteOpen = ref(false);
const recovery = ref<{ title: string; excerpt: string; content: Record<string, unknown>; at: string } | null>(null);
const publishedAt = ref(props.article.published_at);
const status = ref(props.article.status);

let timer: number | null = null;
let localTimer: number | null = null;
let suppress = false;

const statusLabel = computed(() => {
    if (saveState.value === 'saving') return 'Saving…';
    if (saveState.value === 'dirty') return 'Unsaved changes';
    if (saveState.value === 'conflict') return 'Conflict — review needed';
    if (saveState.value === 'error') return 'Save failed';
    return savedAt.value ? `Saved ${relativeTime(savedAt.value)}` : 'All changes saved';
});

const outline = computed(() => {
    const items: { text: string; level: number; index: number }[] = [];
    const walk = (node: any) => {
        if (!node) return;
        if (node.type === 'heading' && node.content) {
            const text = node.content
                .map((child: any) => child.text ?? '')
                .join(' ')
                .trim();
            if (text) items.push({ text, level: node.attrs?.level ?? 2, index: items.length });
        }
        (node.content ?? []).forEach(walk);
    };
    walk(content.value);
    return items;
});

const featured = computed(() => props.media.find((item) => item.id === featuredId.value) ?? null);

const localStorageKey = computed(() => `alarife:draft:${props.article.id}`);

function markDirty() {
    if (suppress) return;
    saveState.value = 'dirty';
    schedule();
    scheduleLocalBackup();
}

function schedule() {
    if (timer) window.clearTimeout(timer);
    timer = window.setTimeout(() => saveNow(), 1400);
}

function scheduleLocalBackup() {
    if (localTimer) window.clearTimeout(localTimer);
    localTimer = window.setTimeout(() => {
        try {
            localStorage.setItem(
                localStorageKey.value,
                JSON.stringify({
                    title: title.value,
                    excerpt: excerpt.value,
                    content: content.value,
                    revision: revision.value,
                    at: new Date().toISOString(),
                }),
            );
        } catch {
            /* storage full or unavailable */
        }
    }, 2500);
}

function onEditorUpdate(value: Record<string, unknown>) {
    content.value = value;
    if (editorRef.value) words.value = editorRef.value.wordCount();
    markDirty();
}

function payload() {
    return {
        title: title.value,
        slug: slug.value,
        excerpt: excerpt.value,
        content: content.value ?? { type: 'doc', content: [] },
        topic_id: topicId.value,
        featured_image_id: featuredId.value,
        revision_number: revision.value,
    };
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function saveNow(explicit = false) {
    if (timer) {
        window.clearTimeout(timer);
        timer = null;
    }
    if (saveState.value === 'conflict') return;
    saveState.value = 'saving';
    try {
        const response = await fetch(props.urls.autosave, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ...payload(), explicit }),
        });

        if (response.status === 409) {
            const data = await response.json();
            conflictMessage.value = data.message ?? 'This article was edited elsewhere.';
            saveState.value = 'conflict';
            return;
        }

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            errorMessage.value = data.message ?? 'The article could not be saved.';
            saveState.value = 'error';
            return;
        }

        const data = await response.json();
        revision.value = data.revision_number;
        savedAt.value = data.saved_at;
        words.value = data.words;
        saveState.value = 'saved';
        errorMessage.value = '';
        localStorage.removeItem(localStorageKey.value);
    } catch {
        saveState.value = 'error';
        errorMessage.value = 'Connection lost. Your draft is stored locally and will retry.';
        schedule();
    }
}

function publish() {
    if (!props.canPublish) return;
    saveNow(true).then(() => {
        router.post(
            props.urls.status,
            { status: 'published' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    status.value = 'published';
                    publishedAt.value = new Date().toISOString();
                },
            },
        );
    });
}

function changeStatus(next: string) {
    router.post(
        props.urls.status,
        { status: next },
        {
            preserveScroll: true,
            onSuccess: () => {
                status.value = next;
                if (next === 'published') publishedAt.value = new Date().toISOString();
            },
        },
    );
}

function restoreRevision(id: number) {
    router.post(route('author.articles.restore', [props.encyclopedia.slug, props.article.id, id]), {}, { preserveScroll: true });
}

function destroy() {
    router.delete(route('author.articles.destroy', [props.encyclopedia.slug, props.article.id]));
}

function handlePickerSelection(items: MediaItem[]) {
    if (!editorRef.value || !items.length) return;
    if (picker.value === 'image') editorRef.value.insertImage(items[0]);
    else editorRef.value.insertGallery(items);
    picker.value = null;
    markDirty();
}

function scrollToHeading(index: number) {
    const headings = document.querySelectorAll('.tiptap h2, .tiptap h3, .tiptap h4');
    headings[index]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function reloadPage() {
    window.location.reload();
}

function discardRecovery() {
    localStorage.removeItem(localStorageKey.value);
    recovery.value = null;
}

function applyRecovery() {
    if (!recovery.value) return;
    suppress = true;
    title.value = recovery.value.title;
    excerpt.value = recovery.value.excerpt;
    content.value = recovery.value.content;
    suppress = false;
    recovery.value = null;
    markDirty();
}

watch([title, slug, excerpt, topicId, featuredId], () => markDirty());

function onKeydown(event: KeyboardEvent) {
    const meta = event.metaKey || event.ctrlKey;
    if (!meta) return;
    if (event.key.toLowerCase() === 's') {
        event.preventDefault();
        saveNow(true);
    }
    if (event.key === 'Enter') {
        event.preventDefault();
        if (props.canPublish) publish();
    }
}

function beforeUnload(event: BeforeUnloadEvent) {
    if (saveState.value === 'dirty' || saveState.value === 'saving') {
        event.preventDefault();
        event.returnValue = '';
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', beforeUnload);
    try {
        const stored = localStorage.getItem(localStorageKey.value);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (parsed && parsed.content && JSON.stringify(parsed.content) !== JSON.stringify(props.article.content)) {
                recovery.value = parsed;
            }
        }
    } catch {
        /* ignore */
    }
    scheduleLocalBackup();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', beforeUnload);
    if (timer) window.clearTimeout(timer);
    if (localTimer) window.clearTimeout(localTimer);
});
</script>

<template>
    <Head :title="`${article.title} · Editor`" />

    <AuthorLayout
        :encyclopedia="encyclopedia"
        active="articles"
        :breadcrumbs="[{ label: 'Articles', href: route('author.articles.index', encyclopedia.slug) }, { label: 'Editor' }]"
    >
        <!-- Editor top bar -->
        <div class="mb-4 flex flex-col gap-3 border-b border-border pb-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <button class="btn-ghost size-8 shrink-0 p-0" type="button" aria-label="Back to articles" @click="router.visit(urls.index)">
                    <ArrowLeft class="size-4" />
                </button>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <StatusBadge :value="status" />
                        <span class="text-[11px] text-muted-foreground">Revision {{ revision }}</span>
                    </div>
                    <p class="font-display mt-0.5 truncate text-lg leading-tight">{{ title || 'Untitled article' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="mr-1 flex items-center gap-1.5 text-[11px]"
                    :class="saveState === 'conflict' || saveState === 'error' ? 'text-destructive' : 'text-muted-foreground'"
                >
                    <Loader2 v-if="saveState === 'saving'" class="size-3 animate-spin" />
                    <Check v-else-if="saveState === 'saved'" class="size-3 text-emerald-600" />
                    <AlertTriangle v-else-if="saveState === 'conflict' || saveState === 'error'" class="size-3" />
                    <Clock3 v-else class="size-3" />
                    {{ statusLabel }}
                </span>
                <a :href="urls.preview" target="_blank" rel="noopener" class="btn-secondary"><ExternalLink class="size-3.5" /> Preview</a>
                <button class="btn-secondary" type="button" :disabled="saveState === 'saving'" @click="saveNow(true)">
                    <Save class="size-3.5" /> Save
                </button>
                <button v-if="canPublish && status !== 'published'" class="btn-primary" type="button" @click="publish">
                    <Rocket class="size-3.5" /> Publish
                </button>
                <button v-else-if="canPublish" class="btn-secondary" type="button" @click="changeStatus('draft')">Unpublish</button>
            </div>
        </div>

        <!-- Banners -->
        <div
            v-if="remoteNotice"
            class="mb-4 flex flex-col gap-2 rounded-lg border border-sky-300 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-200 sm:flex-row sm:items-center"
        >
            <AlertTriangle class="size-4 shrink-0" />
            <p class="flex-1">{{ remoteNotice }} Reload before making further changes, or keep editing and resolve the conflict at save time.</p>
            <div class="flex gap-2">
                <button class="btn-secondary btn-sm" type="button" @click="remoteNotice = null">Keep editing</button>
                <button class="btn-primary btn-sm" type="button" @click="reloadPage">Reload article</button>
            </div>
        </div>
        <div
            v-if="recovery"
            class="mb-4 flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200 sm:flex-row sm:items-center"
        >
            <AlertTriangle class="size-4 shrink-0" />
            <p class="flex-1">An unsaved local draft from {{ relativeTime(recovery.at) }} was found. Restore it or discard it.</p>
            <div class="flex gap-2">
                <button class="btn-secondary btn-sm" type="button" @click="discardRecovery">Discard</button>
                <button class="btn-primary btn-sm" type="button" @click="applyRecovery">Restore draft</button>
            </div>
        </div>
        <div
            v-if="saveState === 'conflict'"
            class="mb-4 flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200 sm:flex-row sm:items-center"
        >
            <AlertTriangle class="size-4 shrink-0" />
            <p class="flex-1">{{ conflictMessage }} Reload to get the latest revision before continuing.</p>
            <button class="btn-secondary btn-sm" type="button" @click="reloadPage">Reload article</button>
        </div>
        <div
            v-if="saveState === 'error'"
            class="mb-4 flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive"
        >
            <AlertTriangle class="size-4" /> {{ errorMessage }} <button class="ml-auto underline" type="button" @click="saveNow(true)">Retry</button>
        </div>

        <div class="grid gap-5 xl:grid-cols-[190px_minmax(0,1fr)_300px]">
            <!-- Outline -->
            <aside class="hidden xl:block">
                <div class="sticky top-24">
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Outline</p>
                    <ul v-if="outline.length" class="space-y-1 border-l border-border">
                        <li v-for="item in outline" :key="`${item.index}-${item.text}`" :style="{ paddingLeft: `${(item.level - 2) * 10 + 10}px` }">
                            <button
                                type="button"
                                class="block w-full truncate py-0.5 text-left text-[11px] text-muted-foreground hover:text-primary"
                                @click="scrollToHeading(item.index)"
                            >
                                {{ item.text }}
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-[11px] text-muted-foreground">Add headings to generate an outline.</p>
                    <div class="mt-5 space-y-1 border-t border-border pt-3 text-[10px] text-muted-foreground">
                        <p>{{ words }} words · {{ article.reading_minutes }} min read</p>
                        <p>Slug /{{ slug }}</p>
                        <p v-if="publishedAt">Published {{ formatDateTime(publishedAt) }}</p>
                    </div>
                </div>
            </aside>

            <!-- Canvas -->
            <section class="min-w-0">
                <div class="mx-auto max-w-[760px]">
                    <input
                        v-model="title"
                        class="font-display w-full border-0 bg-transparent text-[2.2rem] leading-[1.05] tracking-tight outline-none placeholder:text-muted-foreground/40 sm:text-[2.8rem]"
                        placeholder="Article title"
                        aria-label="Article title"
                    />
                    <textarea
                        v-model="excerpt"
                        rows="2"
                        class="mt-2 w-full resize-none border-0 bg-transparent text-sm leading-relaxed text-muted-foreground outline-none placeholder:text-muted-foreground/50"
                        placeholder="A short excerpt for cards and search results…"
                        aria-label="Excerpt"
                    />

                    <div class="sticky top-16 z-20 mt-4 bg-background/90 py-2 backdrop-blur">
                        <EditorToolbar v-if="editorInstance" :editor="editorInstance" @image="picker = 'image'" @gallery="picker = 'gallery'" />
                    </div>

                    <div class="mt-3 rounded-lg border border-border bg-card px-5 py-6 sm:px-8 sm:py-8">
                        <TiptapEditor ref="editorRef" v-model="content" @ready="editorInstance = $event" @update:model-value="onEditorUpdate" />
                    </div>
                </div>
            </section>

            <!-- Properties -->
            <aside class="space-y-4">
                <div class="surface overflow-hidden">
                    <div class="flex border-b border-border">
                        <button
                            class="flex-1 px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.12em]"
                            :class="tab === 'details' ? 'bg-secondary text-foreground' : 'text-muted-foreground'"
                            type="button"
                            @click="tab = 'details'"
                        >
                            Details
                        </button>
                        <button
                            class="flex-1 px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.12em]"
                            :class="tab === 'history' ? 'bg-secondary text-foreground' : 'text-muted-foreground'"
                            type="button"
                            @click="tab = 'history'"
                        >
                            <History class="mr-1 inline size-3" /> History
                        </button>
                    </div>

                    <div v-if="tab === 'details'" class="space-y-4 p-4">
                        <div>
                            <label class="label" for="article-status">Status</label>
                            <select
                                id="article-status"
                                class="input"
                                :value="status"
                                @change="changeStatus(($event.target as HTMLSelectElement).value)"
                            >
                                <option value="draft">Draft</option>
                                <option value="in_review">In review</option>
                                <option v-if="canPublish" value="published">Published</option>
                                <option v-if="canPublish" value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="article-topic">Primary topic</label>
                            <select id="article-topic" v-model="topicId" class="input">
                                <option :value="null">Unassigned</option>
                                <option v-for="topic in topics" :key="topic.id" :value="topic.id">
                                    {{ topic.parent_id ? '— ' : '' }}{{ topic.title }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="article-slug">Slug</label>
                            <input id="article-slug" v-model="slug" class="input font-mono text-xs" />
                        </div>
                        <div>
                            <span class="label">Featured image</span>
                            <div v-if="featured" class="mb-2 overflow-hidden rounded-md border border-border">
                                <img :src="featured.url" :alt="featured.alt_text ?? ''" class="aspect-video w-full object-cover" />
                            </div>
                            <select v-model="featuredId" class="input">
                                <option :value="null">No featured image</option>
                                <option v-for="item in media" :key="item.id" :value="item.id">{{ item.filename }}</option>
                            </select>
                        </div>
                        <div class="rounded-md border border-border bg-secondary/40 p-3 text-[11px] text-muted-foreground">
                            <p class="flex justify-between">
                                <span>Words</span><span class="tabular-nums text-foreground">{{ words }}</span>
                            </p>
                            <p class="mt-1 flex justify-between">
                                <span>Reading time</span><span class="text-foreground">{{ Math.max(1, Math.ceil(words / 220)) }} min</span>
                            </p>
                            <p class="mt-1 flex justify-between">
                                <span>Revision</span><span class="text-foreground">v{{ revision }}</span>
                            </p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="mb-1.5 text-[9px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Search preview</p>
                            <p class="truncate text-[13px] font-medium text-blue-700 dark:text-blue-400">{{ title || 'Article title' }}</p>
                            <p class="truncate text-[10px] text-emerald-700 dark:text-emerald-400">
                                alarife.test/{{ encyclopedia.slug }}/articles/{{ slug || 'slug' }}
                            </p>
                            <p class="mt-1 line-clamp-2 text-[10px] leading-relaxed text-muted-foreground">
                                {{ excerpt || 'Add an excerpt to control the summary shown in search results and cards.' }}
                            </p>
                        </div>
                        <button v-if="canDelete" class="btn-ghost btn-sm w-full text-destructive" type="button" @click="deleteOpen = true">
                            <Trash2 class="size-3.5" /> Delete article
                        </button>
                    </div>

                    <div v-else class="max-h-[60vh] overflow-y-auto">
                        <div v-if="revisions.length" class="divide-y divide-border">
                            <div v-for="rev in revisions" :key="rev.id" class="p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold">Revision {{ rev.revision_number }}</span>
                                    <span class="text-[10px] text-muted-foreground">{{ relativeTime(rev.created_at) }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-[11px] text-muted-foreground">{{ rev.title }} · {{ rev.word_count }} words</p>
                                <div class="mt-1.5 flex items-center justify-between">
                                    <span class="text-[10px] text-muted-foreground">{{ rev.editor ?? 'Unknown' }}</span>
                                    <button
                                        class="text-[10px] font-semibold text-primary hover:underline"
                                        type="button"
                                        @click="restoreRevision(rev.id)"
                                    >
                                        Restore
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="p-6 text-center text-xs text-muted-foreground">No revisions yet.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-primary/20 bg-primary/[0.04] p-3 text-[11px] leading-relaxed text-muted-foreground">
                    <p class="flex items-center gap-1.5 font-semibold text-primary"><ImageIcon class="size-3.5" /> Editorial note</p>
                    <p class="mt-1.5">Images inserted from the library keep their media reference, so changing the theme never breaks them.</p>
                </div>
            </aside>
        </div>

        <MediaPicker
            :open="picker !== null"
            :mode="picker ?? 'image'"
            :media="media"
            :upload-url="route('author.media.store', encyclopedia.slug)"
            @update:open="picker = null"
            @select="handlePickerSelection"
        />

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Delete this article?"
            :description="`“${title}” and its revision history will be removed. This cannot be undone in the interface.`"
            confirm-label="Delete article"
            tone="danger"
            @confirm="destroy"
        />
    </AuthorLayout>
</template>
