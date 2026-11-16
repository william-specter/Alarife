<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDate, relativeTime, wordLabel } from '@/lib/format';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, FileText, Layers, Plus, Search, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

interface EncyclopediaCard {
    id: number;
    title: string;
    subtitle: string | null;
    description: string | null;
    slug: string;
    status: string;
    visibility: string;
    theme_key: string;
    theme_name: string | null;
    cover_url: string | null;
    covers_count: number;
    topics_count: number;
    articles_count: number;
    published_articles_count: number;
    updated_at: string | null;
    latest_release: number | null;
}

const props = defineProps<{
    encyclopedias: EncyclopediaCard[];
    activity: {
        id: number;
        action: string;
        user_name: string;
        encyclopedia_title: string | null;
        subject: string | null;
        created_at: string | null;
    }[];
    stats: { encyclopedias: number; articles: number; published: number; drafts: number };
    filters: { q: string };
}>();

const page = usePage<SharedData>();
const user = page.props.auth.user;
const search = ref(props.filters.q);

function submitSearch() {
    router.get(route('dashboard'), search.value ? { q: search.value } : {}, { preserveState: true, replace: true });
}

const actionLabels: Record<string, string> = {
    'encyclopedia.created': 'created the encyclopedia',
    'encyclopedia.updated': 'updated settings',
    'encyclopedia.published': 'published a release',
    'encyclopedia.unpublished': 'unpublished the encyclopedia',
    'encyclopedia.duplicated': 'duplicated the encyclopedia',
    'encyclopedia.deleted': 'deleted the encyclopedia',
    'article.created': 'created an article',
    'article.saved': 'saved an article',
    'article.published': 'published an article',
    'article.draft': 'moved an article to draft',
    'article.in_review': 'submitted an article for review',
    'article.archived': 'archived an article',
    'article.restored': 'restored a revision',
    'article.deleted': 'deleted an article',
    'topic.created': 'created a topic',
    'topic.updated': 'updated a topic',
    'topic.deleted': 'deleted a topic',
    'theme.updated': 'changed the theme',
    'media.uploaded': 'uploaded an image',
    'export.requested': 'requested an export',
};
</script>

<template>
    <Head title="Your encyclopedias" />

    <AuthorLayout active="dashboard">
        <PageHeader
            eyebrow="Publishers desk"
            title="Your encyclopedias"
            description="Independent reference works, authored once and published everywhere — online and offline."
        >
            <template #actions>
                <Link :href="route('encyclopedias.create')" class="btn-primary"> <Plus class="size-4" /> New encyclopedia </Link>
            </template>
        </PageHeader>

        <div class="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatTile label="Encyclopedias" :value="stats.encyclopedias" hint="Across your membership" />
            <StatTile label="Articles" :value="stats.articles" hint="All editorial content" />
            <StatTile label="Published" :value="stats.published" hint="Visible to readers" accent />
            <StatTile label="In progress" :value="stats.drafts" hint="Drafts and review" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section>
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">Collection</h2>
                    <form class="relative w-full sm:w-72" @submit.prevent="submitSearch">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" class="input pl-9" placeholder="Search by title…" aria-label="Search encyclopedias" />
                    </form>
                </div>

                <div v-if="props.encyclopedias.length" class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="encyclopedia in props.encyclopedias"
                        :key="encyclopedia.id"
                        class="surface group overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <Link :href="route('author.overview', encyclopedia.slug)" class="block">
                            <div class="relative aspect-[16/8] overflow-hidden bg-secondary">
                                <img
                                    v-if="encyclopedia.cover_url"
                                    :src="encyclopedia.cover_url"
                                    :alt="encyclopedia.title"
                                    class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                />
                                <div
                                    v-else
                                    class="flex size-full items-center justify-center bg-[radial-gradient(circle_at_30%_20%,hsl(var(--primary)/0.18),transparent_60%)]"
                                >
                                    <span class="font-display text-4xl text-primary/40">{{ encyclopedia.title.slice(0, 1) }}</span>
                                </div>
                                <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/55 to-transparent" />
                                <div class="absolute left-3 top-3 flex gap-1.5">
                                    <StatusBadge :value="encyclopedia.status" />
                                </div>
                                <span
                                    class="absolute bottom-3 left-3 rounded-full bg-black/35 px-2 py-0.5 text-[10px] font-medium uppercase tracking-[0.12em] text-white backdrop-blur"
                                >
                                    {{ encyclopedia.theme_name ?? encyclopedia.theme_key }}
                                </span>
                            </div>
                        </Link>

                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-display text-xl leading-tight">
                                        <Link :href="route('author.overview', encyclopedia.slug)" class="hover:text-primary">{{
                                            encyclopedia.title
                                        }}</Link>
                                    </h3>
                                    <p v-if="encyclopedia.subtitle" class="mt-0.5 truncate text-xs text-muted-foreground">
                                        {{ encyclopedia.subtitle }}
                                    </p>
                                </div>
                                <Link
                                    :href="route('author.overview', encyclopedia.slug)"
                                    class="btn-secondary btn-sm shrink-0"
                                    aria-label="Open workspace"
                                >
                                    Open <ArrowUpRight class="size-3.5" />
                                </Link>
                            </div>

                            <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                {{ encyclopedia.description || 'No description yet.' }}
                            </p>

                            <div
                                class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-border pt-3 text-[11px] text-muted-foreground"
                            >
                                <span class="flex items-center gap-1"><Layers class="size-3" /> {{ encyclopedia.topics_count }} topics</span>
                                <span class="flex items-center gap-1"
                                    ><FileText class="size-3" /> {{ wordLabel(encyclopedia.articles_count) }} articles</span
                                >
                                <span>{{ encyclopedia.published_articles_count }} live</span>
                                <span class="ml-auto">{{ relativeTime(encyclopedia.updated_at) }}</span>
                            </div>
                        </div>
                    </article>

                    <Link
                        :href="route('encyclopedias.create')"
                        class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border text-center text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5 hover:text-foreground"
                    >
                        <span class="flex size-10 items-center justify-center rounded-full border border-border bg-card"
                            ><Plus class="size-4"
                        /></span>
                        <span class="text-sm font-medium">Start a new encyclopedia</span>
                        <span class="max-w-[220px] text-[11px]">Identity, covers and a theme in one short wizard.</span>
                    </Link>
                </div>

                <EmptyState
                    v-else
                    title="Your shelf is empty"
                    description="Create your first encyclopedia to start writing articles, designing a theme and publishing an offline edition."
                >
                    <Link :href="route('encyclopedias.create')" class="btn-primary"><Plus class="size-4" /> Create encyclopedia</Link>
                </EmptyState>
            </section>

            <aside class="space-y-4">
                <div class="surface p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Recent activity</h2>
                        <Sparkles class="size-3.5 text-primary" />
                    </div>
                    <ol v-if="props.activity.length" class="space-y-3">
                        <li v-for="item in props.activity.slice(0, 8)" :key="item.id" class="relative flex gap-3 border-l border-border pl-3">
                            <span class="absolute -left-[3px] top-1.5 size-1.5 rounded-full bg-primary" aria-hidden="true" />
                            <div class="min-w-0">
                                <p class="text-xs leading-snug">
                                    <span class="font-medium">{{ item.user_name }}</span>
                                    {{ actionLabels[item.action] ?? item.action }}
                                    <span v-if="item.subject" class="font-medium">“{{ item.subject }}”</span>
                                </p>
                                <p class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{ item.encyclopedia_title }} · {{ relativeTime(item.created_at) }}
                                </p>
                            </div>
                        </li>
                    </ol>
                    <p v-else class="py-6 text-center text-xs text-muted-foreground">Activity will appear as you work.</p>
                </div>

                <div class="rounded-xl border border-primary/20 bg-primary/[0.04] p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Publishing principle</p>
                    <p class="font-display mt-2 text-lg leading-snug">Content stays independent of design.</p>
                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                        Change themes, covers and layouts without touching a single article — then export the same edition as a self-contained
                        website.
                    </p>
                    <Link :href="route('encyclopedias.create')" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-primary">
                        Start with a theme <ArrowUpRight class="size-3" />
                    </Link>
                </div>

                <div class="surface p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Account</p>
                    <p class="mt-2 text-xs text-muted-foreground">Signed in as</p>
                    <p class="text-sm font-medium">{{ user.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatDate(user.created_at, { month: 'long', year: 'numeric' }) }} · member</p>
                </div>
            </aside>
        </div>
    </AuthorLayout>
</template>
