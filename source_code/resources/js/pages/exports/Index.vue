<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatBytes, formatDateTime } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Archive, CheckCircle2, Download, HardDrive, Loader2, RefreshCw, ShieldCheck, WifiOff } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface ExportRow {
    id: number;
    status: string;
    progress: number;
    source: string;
    publication_id: number | null;
    options: Record<string, boolean> | null;
    archive_size: number | null;
    checksum: string | null;
    error_message: string | null;
    requested_by: string | null;
    created_at: string | null;
    completed_at: string | null;
    download_url: string | null;
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string; export_enabled: boolean; status: string };
    exports: ExportRow[];
    latestRelease: {
        id: number;
        version: number;
        article_count: number;
        topic_count: number;
        published_at: string | null;
        archive: { id: number; archive_size: number | null; checksum: string | null; completed_at: string | null } | null;
    } | null;
    counts: { articles: number; published: number; topics: number; media: number };
    canExport: boolean;
    canPublish: boolean;
}>();

const rows = ref<ExportRow[]>(JSON.parse(JSON.stringify(props.exports)));
const form = useForm({
    source: (props.latestRelease ? 'release' : 'draft') as 'release' | 'draft',
    include_search: true,
    include_javascript: true,
    include_media: true,
    include_sitemap: true,
});
let poller: number | null = null;

const active = computed(() => rows.value.filter((row) => row.status === 'queued' || row.status === 'processing'));

function requestExport() {
    form.post(route('author.exports.store', props.encyclopedia.slug), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['exports'] });
        },
    });
}

async function poll() {
    for (const row of active.value) {
        try {
            const response = await fetch(route('author.exports.status', [props.encyclopedia.slug, row.id]), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) continue;
            const data = await response.json();
            const target = rows.value.find((item) => item.id === row.id);
            if (target) {
                target.status = data.status;
                target.progress = data.progress;
                target.error_message = data.error_message;
                target.archive_size = data.archive_size;
                target.checksum = data.checksum;
                target.download_url = data.download_url;
                target.completed_at = data.completed_at ?? target.completed_at;
            }
        } catch {
            /* silent */
        }
    }
}

watch(
    () => props.exports,
    (value) => {
        rows.value = JSON.parse(JSON.stringify(value));
    },
    { deep: true },
);

onMounted(() => {
    poller = window.setInterval(poll, 2500);
});
onBeforeUnmount(() => {
    if (poller) window.clearInterval(poller);
});
</script>

<template>
    <Head :title="`Exports · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="exports" :breadcrumbs="[{ label: 'Exports' }]">
        <PageHeader
            eyebrow="Distribution"
            title="Static website export"
            description="A complete offline edition: HTML, CSS, JavaScript, media and local search in one ZIP archive."
        >
            <template #actions>
                <Link v-if="canPublish" :href="route('author.publishing.index', encyclopedia.slug)" class="btn-secondary">Publishing</Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-5">
                <section class="surface overflow-hidden">
                    <div class="border-b border-border px-5 py-4">
                        <h2 class="text-sm font-semibold">Request an archive</h2>
                        <p class="text-[11px] text-muted-foreground">Generation runs in the background; you can leave this page.</p>
                    </div>
                    <form class="space-y-5 p-5" @submit.prevent="requestExport">
                        <div>
                            <span class="label">Source</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="cursor-pointer rounded-lg border p-3"
                                    :class="form.source === 'release' ? 'border-primary bg-primary/5' : 'border-border'"
                                >
                                    <input v-model="form.source" type="radio" value="release" class="sr-only" :disabled="!latestRelease" />
                                    <span class="block text-xs font-semibold">Current release</span>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground">{{
                                        latestRelease
                                            ? `v${latestRelease.version} · ${latestRelease.article_count} articles · stable`
                                            : 'Publish a release first'
                                    }}</span>
                                </label>
                                <label
                                    class="cursor-pointer rounded-lg border p-3"
                                    :class="form.source === 'draft' ? 'border-primary bg-primary/5' : 'border-border'"
                                >
                                    <input v-model="form.source" type="radio" value="draft" class="sr-only" />
                                    <span class="block text-xs font-semibold">Working draft</span>
                                    <span class="mt-0.5 block text-[11px] text-muted-foreground"
                                        >{{ counts.articles }} articles including unpublished</span
                                    >
                                </label>
                            </div>
                        </div>

                        <div>
                            <span class="label">Contents</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex items-start gap-2 rounded-lg border border-border p-2.5">
                                    <input
                                        v-model="form.include_media"
                                        type="checkbox"
                                        class="mt-0.5 size-3.5 rounded border-input text-primary"
                                        disabled
                                    />
                                    <span
                                        ><span class="block text-[11px] font-semibold">All referenced media</span
                                        ><span class="block text-[10px] text-muted-foreground"
                                            >Always included — required for offline reading</span
                                        ></span
                                    >
                                </label>
                                <label class="flex items-start gap-2 rounded-lg border border-border p-2.5">
                                    <input v-model="form.include_search" type="checkbox" class="mt-0.5 size-3.5 rounded border-input text-primary" />
                                    <span
                                        ><span class="block text-[11px] font-semibold">Offline search</span
                                        ><span class="block text-[10px] text-muted-foreground">Local index, no server</span></span
                                    >
                                </label>
                                <label class="flex items-start gap-2 rounded-lg border border-border p-2.5">
                                    <input
                                        v-model="form.include_javascript"
                                        type="checkbox"
                                        class="mt-0.5 size-3.5 rounded border-input text-primary"
                                    />
                                    <span
                                        ><span class="block text-[11px] font-semibold">Interactive features</span
                                        ><span class="block text-[10px] text-muted-foreground">Carousel, navigation, search</span></span
                                    >
                                </label>
                                <label class="flex items-start gap-2 rounded-lg border border-border p-2.5">
                                    <input v-model="form.include_sitemap" type="checkbox" class="mt-0.5 size-3.5 rounded border-input text-primary" />
                                    <span
                                        ><span class="block text-[11px] font-semibold">Sitemap &amp; robots</span
                                        ><span class="block text-[10px] text-muted-foreground">For static hosting</span></span
                                    >
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4 text-xs text-muted-foreground">
                            <span class="flex items-center gap-1.5"><WifiOff class="size-3.5" /> No server, fonts or CDN required.</span>
                            <button class="btn-primary" type="submit" :disabled="!canExport || form.processing">
                                <Archive class="size-4" /> {{ form.processing ? 'Queueing…' : 'Generate archive' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Export history</h2>
                        <button class="btn-ghost btn-sm" type="button" @click="poll"><RefreshCw class="size-3.5" /> Refresh</button>
                    </div>
                    <div v-if="rows.length" class="space-y-2">
                        <article v-for="row in rows" :key="row.id" class="surface p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-md border border-border bg-secondary/60">
                                        <Loader2
                                            v-if="row.status === 'processing' || row.status === 'queued'"
                                            class="size-4 animate-spin text-primary"
                                        />
                                        <CheckCircle2 v-else-if="row.status === 'completed'" class="size-4 text-emerald-600" />
                                        <Archive v-else class="size-4 text-muted-foreground" />
                                    </span>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold">{{ row.source }}</p>
                                            <StatusBadge :value="row.status" />
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                                            {{ formatDateTime(row.created_at) }}
                                            <template v-if="row.requested_by"> · {{ row.requested_by }}</template>
                                            <template v-if="row.archive_size"> · {{ formatBytes(row.archive_size) }}</template>
                                        </p>
                                        <p v-if="row.error_message" class="mt-1 max-w-xl text-[11px] text-destructive">{{ row.error_message }}</p>
                                        <div v-if="row.status === 'processing' || row.status === 'queued'" class="mt-2 w-48">
                                            <div class="h-1 overflow-hidden rounded-full bg-secondary">
                                                <div class="h-full bg-primary transition-all" :style="{ width: `${row.progress}%` }" />
                                            </div>
                                            <p class="mt-1 text-[10px] text-muted-foreground">{{ row.progress }}% — rendering pages and assets</p>
                                        </div>
                                    </div>
                                </div>
                                <a v-if="row.download_url" :href="row.download_url" class="btn-primary btn-sm"
                                    ><Download class="size-3.5" /> Download ZIP</a
                                >
                            </div>
                            <div
                                v-if="row.checksum"
                                class="mt-2 flex items-center gap-1.5 border-t border-border pt-2 font-mono text-[10px] text-muted-foreground"
                            >
                                <ShieldCheck class="size-3 text-emerald-600" /> SHA-256 {{ row.checksum.slice(0, 32) }}…
                            </div>
                        </article>
                    </div>
                    <div v-else class="surface px-5 py-10 text-center text-sm text-muted-foreground">
                        No exports yet. Generate the first archive above.
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <div class="surface p-4">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">What is inside</h2>
                    <ul class="mt-3 space-y-2 text-xs text-muted-foreground">
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-3.5 text-primary" /> Homepage, every topic and every article as directories with
                            index.html.
                        </li>
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-3.5 text-primary" /> Compiled theme CSS using the saved design tokens.
                        </li>
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-3.5 text-primary" /> Referenced images with sanitised, generated filenames.
                        </li>
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-3.5 text-primary" /> Local search index loaded as JavaScript — no fetch calls.
                        </li>
                        <li class="flex items-start gap-2">
                            <CheckCircle2 class="mt-0.5 size-3.5 text-primary" /> sitemap.xml, robots.txt, 404.html and a README.
                        </li>
                    </ul>
                </div>
                <div class="surface p-4">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Reader downloads</h2>
                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                        Public downloads are {{ encyclopedia.export_enabled ? 'enabled' : 'disabled' }}. Readers receive the pre-generated archive of
                        the latest release.
                    </p>
                    <Link :href="route('author.settings', encyclopedia.slug)" class="btn-ghost btn-sm mt-2 px-0">Change in settings →</Link>
                </div>
                <div class="rounded-xl border border-primary/20 bg-primary/[0.04] p-4 text-xs leading-relaxed text-muted-foreground">
                    <p class="flex items-center gap-1.5 font-semibold text-primary"><HardDrive class="size-3.5" /> Tested offline</p>
                    <p class="mt-2">
                        Open the extracted <span class="font-mono text-[10px]">index.html</span> directly from disk. Navigation, carousel and search
                        work without a web server.
                    </p>
                </div>
            </aside>
        </div>
    </AuthorLayout>
</template>
