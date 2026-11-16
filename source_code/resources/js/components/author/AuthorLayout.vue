<script setup lang="ts">
import Toaster from '@/components/Toaster.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useToast } from '@/composables/useToast';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    BookOpen,
    FileText,
    GalleryHorizontal,
    Images,
    LayoutDashboard,
    ListTree,
    Palette,
    Rocket,
    Settings2,
    Sparkles,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface EncyclopediaContext {
    id: number;
    title: string;
    slug: string;
    status?: string;
    theme_name?: string | null;
}

interface Props {
    encyclopedia?: EncyclopediaContext | null;
    active?: string;
    breadcrumbs?: { label: string; href?: string }[];
}

const props = withDefaults(defineProps<Props>(), {
    encyclopedia: null,
    active: '',
    breadcrumbs: () => [],
});

const page = usePage<SharedData & { flash?: { success?: string; error?: string; info?: string } }>();
const user = page.props.auth.user;
const { pushToast } = useToast();
const mobileOpen = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) pushToast('success', flash.success);
        if (flash.error) pushToast('error', flash.error);
        if (flash.info) pushToast('info', flash.info);
    },
    { immediate: true },
);

watch(
    () => page.url,
    () => {
        mobileOpen.value = false;
        window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior });
    },
);

function toggleTheme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    localStorage.setItem('alarife-theme', dark.value ? 'dark' : 'light');
}

type NavLink = { label: string; key: string; href: string; icon: Component };

const workspaceLinks = computed<NavLink[]>(() => {
    if (!props.encyclopedia) return [];
    const e = props.encyclopedia;
    return [
        { label: 'Overview', key: 'overview', href: route('author.overview', e.slug), icon: LayoutDashboard },
        { label: 'Topics', key: 'topics', href: route('author.topics.index', e.slug), icon: ListTree },
        { label: 'Articles', key: 'articles', href: route('author.articles.index', e.slug), icon: FileText },
        { label: 'Media library', key: 'media', href: route('author.media.index', e.slug), icon: Images },
    ];
});

const designLinks = computed<NavLink[]>(() => {
    if (!props.encyclopedia) return [];
    const e = props.encyclopedia;
    return [
        { label: 'Covers', key: 'covers', href: route('author.covers.index', e.slug), icon: GalleryHorizontal },
        { label: 'Appearance', key: 'appearance', href: route('author.appearance.index', e.slug), icon: Palette },
    ];
});

const publishLinks = computed<NavLink[]>(() => {
    if (!props.encyclopedia) return [];
    const e = props.encyclopedia;
    return [
        { label: 'Publishing', key: 'publishing', href: route('author.publishing.index', e.slug), icon: Rocket },
        { label: 'Exports', key: 'exports', href: route('author.exports.index', e.slug), icon: Archive },
        { label: 'Settings', key: 'settings', href: route('author.settings', e.slug), icon: Settings2 },
    ];
});

const viewSiteUrl = computed(() => (props.encyclopedia ? `/${props.encyclopedia.slug}` : null));
const previewUrl = computed(() => (props.encyclopedia ? route('author.preview', props.encyclopedia.slug) : null));
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Mobile backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden" @click="mobileOpen = false" />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[272px] flex-col border-r border-sidebar-border bg-sidebar transition-transform duration-200 lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-md bg-foreground text-background">
                        <span class="font-display text-xl leading-none">A</span>
                    </span>
                    <span class="flex flex-col leading-tight">
                        <span class="font-display text-lg tracking-tight">Alarife</span>
                        <span class="text-[9px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Publishers desk</span>
                    </span>
                </Link>
            </div>

            <div class="flex-1 overflow-y-auto px-3 pb-4">
                <template v-if="encyclopedia">
                    <div class="px-2 pb-3 pt-4">
                        <Link
                            :href="route('dashboard')"
                            class="mb-3 inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft class="size-3" /> All encyclopedias
                        </Link>
                        <div class="rounded-lg border border-sidebar-border bg-card p-3">
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold leading-tight">{{ encyclopedia.title }}</p>
                                <span v-if="encyclopedia.status" :class="encyclopedia.status === 'published' ? 'badge-published' : 'badge-draft'">{{
                                    encyclopedia.status
                                }}</span>
                            </div>
                            <p class="text-[11px] text-muted-foreground">{{ encyclopedia.theme_name ?? 'Workspace' }}</p>
                        </div>
                    </div>

                    <p class="divider-label">Workspace</p>
                    <nav class="space-y-0.5">
                        <Link
                            v-for="item in workspaceLinks"
                            :key="item.key"
                            :href="item.href"
                            class="nav-item"
                            :class="active === item.key ? 'nav-item-active' : ''"
                        >
                            <component :is="item.icon" class="size-4" />
                            {{ item.label }}
                        </Link>
                    </nav>

                    <p class="divider-label">Design</p>
                    <nav class="space-y-0.5">
                        <Link
                            v-for="item in designLinks"
                            :key="item.key"
                            :href="item.href"
                            class="nav-item"
                            :class="active === item.key ? 'nav-item-active' : ''"
                        >
                            <component :is="item.icon" class="size-4" />
                            {{ item.label }}
                        </Link>
                    </nav>

                    <p class="divider-label">Publish</p>
                    <nav class="space-y-0.5">
                        <Link
                            v-for="item in publishLinks"
                            :key="item.key"
                            :href="item.href"
                            class="nav-item"
                            :class="active === item.key ? 'nav-item-active' : ''"
                        >
                            <component :is="item.icon" class="size-4" />
                            {{ item.label }}
                        </Link>
                    </nav>

                    <div class="mt-5 space-y-1.5 rounded-lg border border-dashed border-sidebar-border p-3">
                        <a
                            v-if="viewSiteUrl"
                            :href="viewSiteUrl"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center justify-between text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            <span class="flex items-center gap-2"><BookOpen class="size-3.5" /> View live site</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                        <a
                            v-if="previewUrl"
                            :href="previewUrl"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center justify-between text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            <span class="flex items-center gap-2"><Sparkles class="size-3.5" /> Preview draft</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </template>

                <template v-else>
                    <div class="px-2 pb-2 pt-5">
                        <Link :href="route('encyclopedias.create')" class="btn-primary w-full">
                            <span class="text-base leading-none">+</span> New encyclopedia
                        </Link>
                    </div>
                    <p class="divider-label">Workspace</p>
                    <nav class="space-y-0.5">
                        <Link :href="route('dashboard')" class="nav-item" :class="active === 'dashboard' ? 'nav-item-active' : ''">
                            <LayoutDashboard class="size-4" /> Dashboard
                        </Link>
                        <Link :href="route('profile.edit')" class="nav-item"> <Settings2 class="size-4" /> Account settings </Link>
                    </nav>
                    <div class="mt-6 rounded-lg border border-sidebar-border bg-card p-4">
                        <p class="font-display text-base leading-tight">One desk.<br />Many reference works.</p>
                        <p class="mt-2 text-[11px] leading-relaxed text-muted-foreground">
                            Author once, publish online, and ship a complete offline edition.
                        </p>
                    </div>
                </template>
            </div>

            <div class="border-t border-sidebar-border p-3">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button class="flex w-full items-center gap-3 rounded-md p-2 text-left transition-colors hover:bg-sidebar-accent">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                            >
                                {{ user.name.slice(0, 2).toUpperCase() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-semibold">{{ user.name }}</span>
                                <span class="block truncate text-[10px] text-muted-foreground">{{ user.email }}</span>
                            </span>
                            <span class="text-muted-foreground" aria-hidden="true">⌵</span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent side="top" align="start" class="w-[248px]">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-[272px]">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border/80 bg-background/85 px-4 backdrop-blur-md lg:px-8">
                <button class="btn-ghost size-9 p-0 lg:hidden" type="button" aria-label="Open navigation" @click="mobileOpen = true">
                    <span class="text-lg" aria-hidden="true">☰</span>
                </button>

                <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                    <Link v-if="encyclopedia" :href="route('dashboard')" class="hidden hover:text-foreground sm:inline">Encyclopedias</Link>
                    <span v-if="encyclopedia" class="hidden sm:inline" aria-hidden="true">/</span>
                    <template v-for="(crumb, index) in breadcrumbs" :key="`${crumb.label}-${index}`">
                        <Link v-if="crumb.href" :href="crumb.href" class="truncate hover:text-foreground">{{ crumb.label }}</Link>
                        <span v-else class="truncate text-foreground">{{ crumb.label }}</span>
                        <span v-if="index < breadcrumbs.length - 1" aria-hidden="true">/</span>
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <button
                        class="btn-ghost size-9 p-0"
                        type="button"
                        :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
                        @click="toggleTheme"
                    >
                        <span aria-hidden="true">{{ dark ? '☀' : '☾' }}</span>
                    </button>
                    <Link v-if="viewSiteUrl" :href="viewSiteUrl" target="_blank" class="btn-secondary btn-sm hidden sm:inline-flex">
                        View site <span aria-hidden="true">↗</span>
                    </Link>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                class="ml-1 flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                aria-label="Account menu"
                            >
                                {{ user.name.slice(0, 2).toUpperCase() }}
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1480px] px-4 py-6 lg:px-8 lg:py-9">
                <slot />
            </main>
        </div>

        <Toaster />
    </div>
</template>
