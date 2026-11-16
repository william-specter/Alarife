<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { Check, ExternalLink, Eye, Loader2, Monitor, Palette, RotateCcw, Save, Smartphone, Tablet } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface ThemeOption {
    key: string;
    name: string;
    description: string;
    version: string;
    preview: string;
    defaults: Settings;
}

interface Settings {
    colors: { primary: string; accent: string; background: string; text: string };
    typography: { headingFont: 'serif' | 'sans' | 'mono'; bodyFont: 'serif' | 'sans' | 'mono'; baseSize: number; lineHeight: number };
    layout: { contentWidth: number; density: 'compact' | 'comfortable' | 'spacious'; sidebar: boolean };
    carousel: { autoplay: boolean; transition: 'fade' | 'slide'; showCaptions: boolean };
    accessibility: { reduceMotion: boolean; contrast: 'normal' | 'high' };
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string; status: string; theme_name: string | null };
    themes: ThemeOption[];
    settings: Settings;
    themeKey: string;
    coverStyle: string;
    canManage: boolean;
}>();

const themeKey = ref(props.themeKey);
const coverStyle = ref(props.coverStyle);
const settings = ref<Settings>(JSON.parse(JSON.stringify(props.settings)));
const baseline = ref(JSON.stringify({ themeKey: props.themeKey, coverStyle: props.coverStyle, settings: props.settings }));
const saving = ref(false);
const previewHtml = ref('');
const previewLoading = ref(false);
const previewPage = ref<'home' | 'article' | 'topic'>('home');
const device = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
const previewError = ref<string | null>(null);
let previewTimer: number | null = null;

const selectedTheme = computed(() => props.themes.find((theme) => theme.key === themeKey.value) ?? props.themes[0]);
const dirty = computed(() => JSON.stringify({ themeKey: themeKey.value, coverStyle: coverStyle.value, settings: settings.value }) !== baseline.value);

const deviceWidth = computed(() => (device.value === 'mobile' ? '390px' : device.value === 'tablet' ? '820px' : '100%'));

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function loadPreview() {
    previewLoading.value = true;
    previewError.value = null;
    try {
        const response = await fetch(route('author.preview.frame', props.encyclopedia.slug), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ page: previewPage.value, theme_key: themeKey.value, settings: settings.value, cover_style: coverStyle.value }),
        });
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message ?? 'Unable to render the preview.');
        }
        const data = await response.json();
        previewHtml.value = data.html;
    } catch (error) {
        previewError.value = error instanceof Error ? error.message : 'Preview failed.';
    } finally {
        previewLoading.value = false;
    }
}

function schedulePreview() {
    if (previewTimer) window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(loadPreview, 450);
}

watch([settings, themeKey, coverStyle, previewPage], () => schedulePreview(), { deep: true });

function chooseTheme(theme: ThemeOption) {
    if (theme.key === themeKey.value) return;
    themeKey.value = theme.key;
    settings.value = JSON.parse(JSON.stringify(theme.defaults));
}

function resetTheme() {
    settings.value = JSON.parse(JSON.stringify(selectedTheme.value.defaults));
}

function save() {
    saving.value = true;
    router.put(
        route('author.appearance.update', props.encyclopedia.slug),
        {
            theme_key: themeKey.value,
            cover_style: coverStyle.value,
            settings: settings.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                baseline.value = JSON.stringify({ themeKey: themeKey.value, coverStyle: coverStyle.value, settings: settings.value });
            },
            onFinish: () => (saving.value = false),
        },
    );
}

function beforeUnload(event: BeforeUnloadEvent) {
    if (dirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}

onMounted(() => {
    loadPreview();
    window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => {
    if (previewTimer) window.clearTimeout(previewTimer);
    window.removeEventListener('beforeunload', beforeUnload);
});

const previewUrl = computed(() => route('author.preview', props.encyclopedia.slug));
</script>

<template>
    <Head :title="`Appearance · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="appearance" :breadcrumbs="[{ label: 'Appearance' }]">
        <PageHeader
            eyebrow="Design"
            title="Appearance"
            description="Choose a template and tune its tokens. The preview updates as you work; nothing is saved until you say so."
        >
            <template #actions>
                <a :href="previewUrl" target="_blank" rel="noopener" class="btn-secondary"><ExternalLink class="size-4" /> Open preview</a>
                <button class="btn-primary" type="button" :disabled="!canManage || saving || !dirty" @click="save">
                    <Loader2 v-if="saving" class="size-4 animate-spin" /><Save v-else class="size-4" />
                    {{ saving ? 'Saving…' : dirty ? 'Save theme' : 'Saved' }}
                </button>
            </template>
        </PageHeader>

        <!-- Theme gallery -->
        <section class="mb-6">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <button
                    v-for="theme in themes"
                    :key="theme.key"
                    type="button"
                    class="group overflow-hidden rounded-xl border text-left transition-all"
                    :class="themeKey === theme.key ? 'border-primary ring-2 ring-primary/15' : 'border-border hover:border-primary/40'"
                    :disabled="!canManage"
                    @click="chooseTheme(theme)"
                >
                    <div class="relative aspect-[16/10] overflow-hidden bg-secondary">
                        <img
                            :src="theme.preview"
                            :alt="`${theme.name} preview`"
                            class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                        />
                        <span
                            v-if="themeKey === theme.key"
                            class="absolute right-2 top-2 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground shadow"
                            ><Check class="size-3.5"
                        /></span>
                        <span
                            class="absolute left-2 top-2 rounded-full bg-black/45 px-2 py-0.5 text-[9px] font-medium uppercase tracking-[0.1em] text-white backdrop-blur"
                            >v{{ theme.version }}</span
                        >
                    </div>
                    <div class="p-2.5">
                        <p class="truncate text-xs font-semibold">{{ theme.name }}</p>
                        <p class="mt-0.5 line-clamp-2 text-[10px] leading-snug text-muted-foreground">{{ theme.description }}</p>
                    </div>
                </button>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[330px_minmax(0,1fr)]">
            <!-- Controls -->
            <aside class="space-y-4">
                <div class="surface p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            <Palette class="size-3.5" /> Theme settings
                        </h2>
                        <button
                            class="text-[10px] font-medium text-muted-foreground hover:text-foreground"
                            type="button"
                            :disabled="!canManage"
                            @click="resetTheme"
                        >
                            <RotateCcw class="mr-0.5 inline size-3" /> Reset
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="label">Colours</p>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="key in ['primary', 'accent', 'background', 'text'] as const"
                                    :key="key"
                                    class="flex items-center gap-2 rounded-md border border-border p-1.5"
                                >
                                    <input
                                        v-model="settings.colors[key]"
                                        type="color"
                                        class="size-7 cursor-pointer rounded border-0 bg-transparent p-0"
                                        :aria-label="`${key} colour`"
                                        :disabled="!canManage"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-[9px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{
                                            key
                                        }}</span>
                                        <input
                                            v-model="settings.colors[key]"
                                            class="w-full border-0 bg-transparent p-0 font-mono text-[10px] outline-none"
                                            :disabled="!canManage"
                                        />
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <p class="label">Typography</p>
                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="settings.typography.headingFont" class="input" aria-label="Heading font" :disabled="!canManage">
                                    <option value="serif">Serif headings</option>
                                    <option value="sans">Sans headings</option>
                                    <option value="mono">Mono headings</option>
                                </select>
                                <select v-model="settings.typography.bodyFont" class="input" aria-label="Body font" :disabled="!canManage">
                                    <option value="serif">Serif body</option>
                                    <option value="sans">Sans body</option>
                                    <option value="mono">Mono body</option>
                                </select>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <label class="text-[10px] text-muted-foreground"
                                    >Size {{ settings.typography.baseSize }}px
                                    <input
                                        v-model.number="settings.typography.baseSize"
                                        type="range"
                                        min="14"
                                        max="22"
                                        step="1"
                                        class="mt-1 w-full accent-[hsl(var(--primary))]"
                                        :disabled="!canManage"
                                    />
                                </label>
                                <label class="text-[10px] text-muted-foreground"
                                    >Line {{ settings.typography.lineHeight }}
                                    <input
                                        v-model.number="settings.typography.lineHeight"
                                        type="range"
                                        min="1.3"
                                        max="2"
                                        step="0.05"
                                        class="mt-1 w-full accent-[hsl(var(--primary))]"
                                        :disabled="!canManage"
                                    />
                                </label>
                            </div>
                        </div>

                        <div>
                            <p class="label">Layout</p>
                            <label class="text-[10px] text-muted-foreground"
                                >Content width {{ settings.layout.contentWidth }}px
                                <input
                                    v-model.number="settings.layout.contentWidth"
                                    type="range"
                                    min="880"
                                    max="1440"
                                    step="20"
                                    class="mt-1 w-full accent-[hsl(var(--primary))]"
                                    :disabled="!canManage"
                                />
                            </label>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <select v-model="settings.layout.density" class="input" aria-label="Density" :disabled="!canManage">
                                    <option value="compact">Compact</option>
                                    <option value="comfortable">Comfortable</option>
                                    <option value="spacious">Spacious</option>
                                </select>
                                <select v-model="settings.layout.sidebar" class="input" aria-label="Sidebar" :disabled="!canManage">
                                    <option :value="true">Topic sidebar</option>
                                    <option :value="false">No sidebar</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <p class="label">Carousel</p>
                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="settings.carousel.transition" class="input" aria-label="Carousel transition" :disabled="!canManage">
                                    <option value="fade">Fade transition</option>
                                    <option value="slide">Slide transition</option>
                                </select>
                                <select v-model="coverStyle" class="input" aria-label="Cover layout" :disabled="!canManage">
                                    <option value="hero">Hero cover</option>
                                    <option value="editorial">Editorial cover</option>
                                    <option value="cards">Card covers</option>
                                </select>
                            </div>
                            <div class="mt-2 space-y-1.5">
                                <label class="flex items-center gap-2 text-[11px]"
                                    ><input
                                        v-model="settings.carousel.autoplay"
                                        type="checkbox"
                                        class="size-3.5 rounded border-input text-primary"
                                        :disabled="!canManage"
                                    />
                                    Autoplay covers</label
                                >
                                <label class="flex items-center gap-2 text-[11px]"
                                    ><input
                                        v-model="settings.carousel.showCaptions"
                                        type="checkbox"
                                        class="size-3.5 rounded border-input text-primary"
                                        :disabled="!canManage"
                                    />
                                    Show captions</label
                                >
                            </div>
                        </div>

                        <div>
                            <p class="label">Accessibility</p>
                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="settings.accessibility.contrast" class="input" aria-label="Contrast" :disabled="!canManage">
                                    <option value="normal">Normal contrast</option>
                                    <option value="high">High contrast</option>
                                </select>
                                <label class="flex items-center gap-2 rounded-md border border-border px-2 text-[11px]">
                                    <input
                                        v-model="settings.accessibility.reduceMotion"
                                        type="checkbox"
                                        class="size-3.5 rounded border-input text-primary"
                                        :disabled="!canManage"
                                    />
                                    Reduced motion
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="surface p-4 text-[11px] leading-relaxed text-muted-foreground">
                    <p class="mb-1 font-semibold text-foreground">Design stays separate</p>
                    <p>Switching a template only changes the presentation layer. Articles, media references and URLs are untouched.</p>
                </div>
            </aside>

            <!-- Preview -->
            <section class="surface overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-secondary/40 px-3 py-2">
                    <div class="flex items-center gap-1">
                        <button
                            v-for="page in ['home', 'topic', 'article'] as const"
                            :key="page"
                            type="button"
                            class="rounded px-2.5 py-1 text-[11px] font-medium capitalize"
                            :class="previewPage === page ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground'"
                            @click="previewPage = page"
                        >
                            {{ page }}
                        </button>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="rounded p-1.5"
                            :class="device === 'desktop' ? 'bg-card shadow-sm' : 'text-muted-foreground'"
                            title="Desktop"
                            @click="device = 'desktop'"
                        >
                            <Monitor class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="rounded p-1.5"
                            :class="device === 'tablet' ? 'bg-card shadow-sm' : 'text-muted-foreground'"
                            title="Tablet"
                            @click="device = 'tablet'"
                        >
                            <Tablet class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="rounded p-1.5"
                            :class="device === 'mobile' ? 'bg-card shadow-sm' : 'text-muted-foreground'"
                            title="Mobile"
                            @click="device = 'mobile'"
                        >
                            <Smartphone class="size-3.5" />
                        </button>
                        <span class="mx-1 h-4 w-px bg-border" />
                        <button
                            type="button"
                            class="rounded p-1.5 text-muted-foreground hover:text-foreground"
                            title="Refresh preview"
                            @click="loadPreview"
                        >
                            <Eye class="size-3.5" />
                        </button>
                    </div>
                </div>
                <div
                    class="relative flex justify-center overflow-hidden bg-[radial-gradient(circle_at_20%_10%,hsl(var(--primary)/0.07),transparent_50%)] p-3 sm:p-5"
                >
                    <div v-if="previewLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-background/50 backdrop-blur-[1px]">
                        <Loader2 class="size-5 animate-spin text-primary" />
                    </div>
                    <div
                        class="overflow-hidden rounded-lg border border-border bg-white shadow-xl transition-[width] duration-300"
                        :style="{ width: deviceWidth, height: '72vh', maxWidth: '100%' }"
                    >
                        <iframe
                            v-if="previewHtml"
                            :srcdoc="previewHtml"
                            title="Theme preview"
                            class="size-full bg-white"
                            sandbox="allow-scripts allow-same-origin"
                        />
                        <div
                            v-else-if="previewError"
                            class="flex size-full items-center justify-center p-6 text-center text-xs text-muted-foreground"
                        >
                            {{ previewError }}
                        </div>
                        <div v-else class="flex size-full items-center justify-center text-xs text-muted-foreground">Rendering preview…</div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-border px-3 py-2 text-[10px] text-muted-foreground">
                    <span>{{ selectedTheme?.name }} · {{ selectedTheme?.version }}</span>
                    <span>{{ dirty ? 'Unsaved changes' : 'All changes saved' }}</span>
                </div>
            </section>
        </div>
    </AuthorLayout>
</template>
