<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatBytes, formatDateTime } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Archive, Check, Circle, Download, Rocket, ShieldCheck, Undo2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string; status: string; theme_name: string | null };
    readiness: { key: string; label: string; done: boolean; blocking: boolean }[];
    releases: {
        id: number;
        version: number;
        published_at: string | null;
        article_count: number;
        topic_count: number;
        theme_key: string;
        theme_version: string;
        notes: string | null;
        publisher: string | null;
        archive: { id: number; size: number | null; checksum: string | null } | null;
    }[];
    canPublish: boolean;
    archiveReady: boolean;
}>();

const form = useForm({ notes: '' });
const blocking = computed(() => props.readiness.filter((item) => !item.done && item.blocking));

function publish() {
    form.post(route('author.publishing.store', props.encyclopedia.slug), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function unpublish() {
    router.post(route('author.publishing.unpublish', props.encyclopedia.slug), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Publishing · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="publishing" :breadcrumbs="[{ label: 'Publishing' }]">
        <PageHeader
            eyebrow="Release"
            title="Publishing"
            description="A release is an immutable snapshot of published articles, topics, covers and theme settings."
        >
            <template #actions>
                <Link :href="route('author.exports.index', encyclopedia.slug)" class="btn-secondary"><Archive class="size-4" /> Exports</Link>
                <button v-if="canPublish && encyclopedia.status === 'published'" class="btn-secondary" type="button" @click="unpublish">
                    <Undo2 class="size-4" /> Unpublish
                </button>
            </template>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-5">
                <section class="surface overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold">New release</h2>
                            <p class="text-[11px] text-muted-foreground">Creates a permanent snapshot and builds the downloadable archive.</p>
                        </div>
                        <StatusBadge :value="encyclopedia.status === 'published' ? 'published' : 'draft'" />
                    </div>
                    <form class="space-y-3 p-5" @submit.prevent="publish">
                        <div>
                            <label class="label" for="release-notes"
                                >Release notes <span class="normal-case tracking-normal text-muted-foreground/60">(optional)</span></label
                            >
                            <input
                                id="release-notes"
                                v-model="form.notes"
                                class="input"
                                placeholder="What changed in this edition?"
                                :disabled="!canPublish"
                            />
                            <p v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</p>
                            <p v-if="(form.errors as any).publish" class="field-error">{{ (form.errors as any).publish }}</p>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-secondary/40 px-3 py-2.5 text-[11px] text-muted-foreground"
                        >
                            <span class="flex items-center gap-1.5"
                                ><ShieldCheck class="size-3.5 text-primary" /> Snapshots are write-once and versioned.</span
                            >
                            <span>{{ releases.length ? `Next version: v${releases[0].version + 1}` : 'First release: v1' }}</span>
                        </div>
                        <button class="btn-primary w-full" type="submit" :disabled="!canPublish || form.processing || blocking.length > 0">
                            <Rocket class="size-4" /> {{ form.processing ? 'Publishing…' : 'Publish release' }}
                        </button>
                        <p v-if="blocking.length" class="text-center text-[11px] text-destructive">
                            Complete the required readiness items before publishing.
                        </p>
                    </form>
                </section>

                <section>
                    <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Release history</h2>
                    <div v-if="releases.length" class="space-y-2.5">
                        <article v-for="release in releases" :key="release.id" class="surface p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="font-display flex size-10 shrink-0 items-center justify-center rounded-md bg-foreground text-sm text-background"
                                        >v{{ release.version }}</span
                                    >
                                    <div>
                                        <p class="text-sm font-semibold">{{ release.article_count }} articles · {{ release.topic_count }} topics</p>
                                        <p class="text-[11px] text-muted-foreground">
                                            {{ formatDateTime(release.published_at) }} · {{ release.publisher ?? 'Unknown' }} ·
                                            {{ release.theme_key }} {{ release.theme_version }}
                                        </p>
                                        <p v-if="release.notes" class="mt-1 text-xs italic text-muted-foreground">“{{ release.notes }}”</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a
                                        v-if="release.archive"
                                        :href="route('author.exports.download', [encyclopedia.slug, release.archive.id])"
                                        class="btn-secondary btn-sm"
                                        ><Download class="size-3.5" /> {{ formatBytes(release.archive.size) }}</a
                                    >
                                    <span v-else class="badge-draft"><Archive class="size-3" /> Archive pending</span>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="surface px-5 py-10 text-center text-sm text-muted-foreground">
                        No releases yet. Publishing creates the first immutable edition.
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <div class="surface p-4">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Readiness</h2>
                    <ul class="mt-3 space-y-2">
                        <li v-for="item in readiness" :key="item.key" class="flex items-start gap-2 text-xs">
                            <span
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full"
                                :class="item.done ? 'bg-primary text-primary-foreground' : 'border border-border'"
                            >
                                <Check v-if="item.done" class="size-2.5" /><Circle v-else class="size-2 text-muted-foreground" />
                            </span>
                            <span :class="item.done ? 'text-muted-foreground line-through decoration-border' : 'text-foreground'">{{
                                item.label
                            }}</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-xl border border-primary/20 bg-primary/[0.04] p-4 text-xs leading-relaxed text-muted-foreground">
                    <p class="font-semibold text-primary">What a release pins</p>
                    <p class="mt-1.5">
                        Encyclopedia metadata, cover order, topic tree, published article revisions, media references and the exact theme version with
                        its settings.
                    </p>
                    <p class="mt-2">Drafts never enter a release, and changing content later does not mutate history.</p>
                </div>
                <div class="surface p-4 text-xs text-muted-foreground">
                    <p class="font-semibold text-foreground">Archive status</p>
                    <p class="mt-1">
                        {{
                            archiveReady
                                ? 'The current release has a downloadable archive.'
                                : 'The archive will be generated automatically after publishing.'
                        }}
                    </p>
                </div>
            </aside>
        </div>
    </AuthorLayout>
</template>
