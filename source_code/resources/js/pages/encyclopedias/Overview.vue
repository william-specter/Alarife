<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useEncyclopediaRealtime } from '@/composables/useRealtime';
import { formatDate, relativeTime, wordLabel } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Circle, FileText, Plus, Rocket, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    encyclopedia: {
        id: number;
        title: string;
        subtitle: string | null;
        description: string | null;
        slug: string;
        status: string;
        visibility: string;
        theme_name: string | null;
        cover_url: string | null;
        covers_count: number;
        published_at: string | null;
        updated_at: string | null;
    };
    role: string | null;
    counts: { topics: number; articles: number; published: number; drafts: number; words: number };
    recentArticles: { id: number; title: string; slug: string; status: string; topic: string | null; updated_at: string | null; words: number }[];
    latestRelease: { version: number; published_at: string | null; article_count: number; topic_count: number } | null;
    readiness: { key: string; label: string; done: boolean; blocking: boolean }[];
    canPublish: boolean;
    canEdit: boolean;
}>();

let refreshTimer: number | null = null;

useEncyclopediaRealtime(props.encyclopedia.id, () => {
    if (refreshTimer) window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        router.reload({
            only: ['encyclopedia', 'counts', 'recentArticles', 'latestRelease', 'readiness'],
        });
    }, 300);
});

const readyCount = computed(() => props.readiness.filter((item) => item.done).length);
const readyPercent = computed(() => Math.round((readyCount.value / props.readiness.length) * 100));
const blocking = computed(() => props.readiness.filter((item) => !item.done && item.blocking));
</script>

<template>
    <Head :title="encyclopedia.title" />

    <AuthorLayout :encyclopedia="encyclopedia" active="overview" :breadcrumbs="[{ label: encyclopedia.title }]">
        <PageHeader
            eyebrow="Workspace"
            title="Overview"
            :description="encyclopedia.description ?? 'Shape this encyclopedia, then publish it online and offline.'"
        >
            <template #actions>
                <a :href="route('author.preview', encyclopedia.slug)" target="_blank" rel="noopener" class="btn-secondary">
                    <Sparkles class="size-4" /> Preview
                </a>
                <Link v-if="canPublish" :href="route('author.publishing.index', encyclopedia.slug)" class="btn-primary">
                    <Rocket class="size-4" /> Publishing
                </Link>
            </template>
        </PageHeader>

        <!-- Editorial hero -->
        <section class="surface relative mb-6 overflow-hidden">
            <div class="absolute inset-0">
                <img v-if="encyclopedia.cover_url" :src="encyclopedia.cover_url" alt="" class="size-full object-cover" />
                <div v-else class="size-full bg-[radial-gradient(circle_at_20%_20%,hsl(var(--primary)/0.22),transparent_55%)]" />
                <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/55 to-black/20" />
            </div>
            <div class="relative flex flex-col justify-between gap-8 p-6 text-white sm:p-8 lg:min-h-[260px] lg:flex-row lg:items-end">
                <div class="max-w-2xl">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <StatusBadge :value="encyclopedia.status" />
                        <span
                            class="rounded-full border border-white/25 bg-white/10 px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-[0.12em] backdrop-blur"
                            >{{ encyclopedia.theme_name }}</span
                        >
                        <span
                            class="rounded-full border border-white/25 bg-white/10 px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-[0.12em] backdrop-blur"
                            >{{ encyclopedia.visibility }}</span
                        >
                    </div>
                    <h1 class="font-display text-[2.2rem] leading-[1.02] tracking-tight sm:text-[3rem]">{{ encyclopedia.title }}</h1>
                    <p v-if="encyclopedia.subtitle" class="font-display mt-1 text-lg italic text-white/80">{{ encyclopedia.subtitle }}</p>
                    <p class="mt-3 max-w-xl text-sm leading-relaxed text-white/75">
                        {{
                            encyclopedia.description ||
                            'No description yet — add one in settings so readers understand the scope of this reference work.'
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('author.articles.index', encyclopedia.slug)" class="btn bg-white text-stone-900 hover:bg-white/90">
                        <Plus class="size-4" /> Write an article
                    </Link>
                    <Link
                        v-if="canEdit"
                        :href="route('author.covers.index', encyclopedia.slug)"
                        class="btn border border-white/30 bg-white/10 text-white hover:bg-white/20"
                    >
                        Manage covers
                    </Link>
                </div>
            </div>
        </section>

        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatTile label="Topics" :value="counts.topics" hint="Content structure" />
            <StatTile label="Articles" :value="counts.articles" :hint="`${counts.published} published · ${counts.drafts} in progress`" />
            <StatTile label="Words" :value="wordLabel(counts.words)" hint="Across all articles" />
            <StatTile
                label="Release"
                :value="latestRelease ? `v${latestRelease.version}` : '—'"
                :hint="latestRelease ? formatDate(latestRelease.published_at) : 'Not published yet'"
                accent
            />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="surface overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold">Recent articles</h2>
                        <p class="text-[11px] text-muted-foreground">Continue where the work stopped.</p>
                    </div>
                    <Link :href="route('author.articles.index', encyclopedia.slug)" class="btn-ghost btn-sm"
                        >All articles <ArrowUpRight class="size-3.5"
                    /></Link>
                </div>
                <div v-if="recentArticles.length" class="divide-y divide-border">
                    <Link
                        v-for="article in recentArticles"
                        :key="article.id"
                        :href="route('author.articles.edit', [encyclopedia.slug, article.id])"
                        class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-secondary/60"
                    >
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-secondary/60"
                            ><FileText class="size-3.5"
                        /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ article.title }}</span>
                            <span class="block text-[11px] text-muted-foreground"
                                >{{ article.topic ?? 'Unassigned' }} · {{ wordLabel(article.words) }} words ·
                                {{ relativeTime(article.updated_at) }}</span
                            >
                        </span>
                        <StatusBadge :value="article.status" />
                    </Link>
                </div>
                <div v-else class="px-5 py-10 text-center">
                    <p class="text-sm text-muted-foreground">No articles yet.</p>
                    <Link :href="route('author.articles.index', encyclopedia.slug)" class="btn-primary btn-sm mt-3"
                        ><Plus class="size-3.5" /> Write the first article</Link
                    >
                </div>
            </section>

            <aside class="space-y-4">
                <div class="surface p-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Release readiness</h2>
                        <span class="text-xs font-semibold tabular-nums">{{ readyPercent }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-secondary">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${readyPercent}%` }" />
                    </div>
                    <ul class="mt-3 space-y-2">
                        <li v-for="item in readiness" :key="item.key" class="flex items-start gap-2 text-xs">
                            <span
                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full"
                                :class="item.done ? 'bg-primary text-primary-foreground' : 'border border-border text-muted-foreground'"
                            >
                                <Check v-if="item.done" class="size-2.5" />
                                <Circle v-else class="size-2" />
                            </span>
                            <span :class="item.done ? 'text-muted-foreground line-through decoration-border' : 'text-foreground'">{{
                                item.label
                            }}</span>
                        </li>
                    </ul>
                    <p v-if="blocking.length" class="mt-3 text-[11px] leading-relaxed text-muted-foreground">
                        {{ blocking.length }} required {{ blocking.length === 1 ? 'item' : 'items' }} before publishing a release.
                    </p>
                    <Link v-if="canPublish" :href="route('author.publishing.index', encyclopedia.slug)" class="btn-secondary btn-sm mt-3 w-full"
                        >Open publishing</Link
                    >
                </div>

                <div class="surface p-4">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Latest release</h2>
                    <template v-if="latestRelease">
                        <p class="font-display mt-2 text-3xl">v{{ latestRelease.version }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ formatDate(latestRelease.published_at) }} · {{ latestRelease.article_count }} articles
                        </p>
                        <Link :href="route('author.publishing.index', encyclopedia.slug)" class="btn-ghost btn-sm mt-2 px-0"
                            >Release history <ArrowUpRight class="size-3.5"
                        /></Link>
                    </template>
                    <p v-else class="mt-2 text-xs text-muted-foreground">
                        This encyclopedia has not been published yet. Releases are immutable snapshots used for online reading and archives.
                    </p>
                </div>

                <div class="rounded-xl border border-primary/20 bg-primary/[0.04] p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Quick actions</p>
                    <div class="mt-2 grid gap-1.5 text-xs">
                        <Link
                            :href="route('author.topics.index', encyclopedia.slug)"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-primary/5"
                            ><span>Manage topics</span><span aria-hidden="true">→</span></Link
                        >
                        <Link
                            :href="route('author.media.index', encyclopedia.slug)"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-primary/5"
                            ><span>Media library</span><span aria-hidden="true">→</span></Link
                        >
                        <Link
                            :href="route('author.appearance.index', encyclopedia.slug)"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-primary/5"
                            ><span>Change theme</span><span aria-hidden="true">→</span></Link
                        >
                        <Link
                            :href="route('author.exports.index', encyclopedia.slug)"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-primary/5"
                            ><span>Export website</span><span aria-hidden="true">→</span></Link
                        >
                    </div>
                </div>
            </aside>
        </div>
    </AuthorLayout>
</template>
