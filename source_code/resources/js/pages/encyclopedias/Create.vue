<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import CrossReferencePicker from '@/components/CrossReferencePicker.vue';
import PageHeader from '@/components/PageHeader.vue';
import type { CrossReferenceSelection } from '@/types/references';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Globe2, Image, LayoutTemplate, Lock, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface ThemeOption {
    key: string;
    name: string;
    description: string;
    version: string;
    accent: string;
    background: string;
    primary: string;
    preview: string;
}

const props = defineProps<{
    themes: ThemeOption[];
    languages: { code: string; label: string }[];
}>();

const step = ref(1);
const slugTouched = ref(false);

const form = useForm({
    title: '',
    subtitle: '',
    slug: '',
    description: '',
    language: 'en',
    visibility: 'private' as 'private' | 'unlisted' | 'public',
    theme_key: props.themes[0]?.key ?? 'classic',
    cover_style: 'hero' as 'hero' | 'editorial' | 'cards',
    export_enabled: true,
    references: [] as CrossReferenceSelection[],
});

const selectedTheme = computed(() => props.themes.find((theme) => theme.key === form.theme_key) ?? props.themes[0]);

watch(
    () => form.title,
    (title) => {
        if (slugTouched.value) return;
        form.slug = title
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 80);
    },
);

function next() {
    if (step.value === 1) {
        if (!form.title || form.title.length < 3) {
            form.setError('title', 'Give your encyclopedia a title of at least 3 characters.');
            return;
        }
        form.clearErrors();
    }
    step.value = Math.min(3, step.value + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function back() {
    step.value = Math.max(1, step.value - 1);
}

function submit() {
    form.post(route('encyclopedias.store'));
}

const visibilityOptions = [
    { value: 'private', label: 'Private', description: 'Only you and invited collaborators.', icon: Lock },
    { value: 'unlisted', label: 'Unlisted', description: 'Anyone with the link can read it.', icon: Globe2 },
    { value: 'public', label: 'Public', description: 'Listed publicly and indexable.', icon: Sparkles },
];
</script>

<template>
    <Head title="New encyclopedia" />

    <AuthorLayout active="create">
        <PageHeader
            eyebrow="New reference work"
            title="Start an encyclopedia"
            description="Three short steps: identity, design and review. You can refine everything later in the workspace."
        >
            <template #actions>
                <Link :href="route('dashboard')" class="btn-ghost"><ArrowLeft class="size-4" /> Cancel</Link>
            </template>
        </PageHeader>

        <div class="mb-6 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.14em]">
            <span
                v-for="item in [1, 2, 3]"
                :key="item"
                class="flex items-center gap-2"
                :class="step >= item ? 'text-foreground' : 'text-muted-foreground/60'"
            >
                <span
                    class="flex size-6 items-center justify-center rounded-full border"
                    :class="step > item ? 'border-primary bg-primary text-primary-foreground' : step === item ? 'border-foreground' : 'border-border'"
                >
                    <Check v-if="step > item" class="size-3" />
                    <template v-else>{{ item }}</template>
                </span>
                {{ item === 1 ? 'Identity' : item === 2 ? 'Design' : 'Review' }}
                <span v-if="item < 3" class="ml-1 h-px w-8 bg-border" aria-hidden="true" />
            </span>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="surface p-5 sm:p-7">
                <!-- Step 1 -->
                <div v-if="step === 1" class="space-y-5">
                    <div>
                        <label class="label" for="title">Title</label>
                        <input id="title" v-model="form.title" class="input text-base" placeholder="Ancient Civilizations" autofocus />
                        <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="subtitle"
                                >Subtitle <span class="normal-case tracking-normal text-muted-foreground/60">(optional)</span></label
                            >
                            <input id="subtitle" v-model="form.subtitle" class="input" placeholder="A visual reference" />
                            <p v-if="form.errors.subtitle" class="field-error">{{ form.errors.subtitle }}</p>
                        </div>
                        <div>
                            <label class="label" for="language">Language</label>
                            <select id="language" v-model="form.language" class="input">
                                <option v-for="language in props.languages" :key="language.code" :value="language.code">{{ language.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="slug">URL slug</label>
                        <div
                            class="flex items-center rounded-md border border-input bg-card shadow-sm focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/15"
                        >
                            <span class="pl-3 text-xs text-muted-foreground">alarife.test/</span>
                            <input
                                id="slug"
                                v-model="form.slug"
                                class="h-9 min-w-0 flex-1 border-0 bg-transparent px-1.5 text-sm outline-none"
                                placeholder="ancient-civilizations"
                                @input="slugTouched = true"
                            />
                        </div>
                        <p v-if="form.errors.slug" class="field-error">{{ form.errors.slug }}</p>
                        <p v-else class="field-hint">Lowercase letters, numbers and dashes. Reserved words are rejected.</p>
                    </div>
                    <div>
                        <label class="label" for="description">Description</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="textarea"
                            placeholder="What does this encyclopedia cover? A sentence or two for the cover and search engines."
                        />
                        <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                    </div>
                    <div>
                        <span class="label">Access</span>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label
                                v-for="option in visibilityOptions"
                                :key="option.value"
                                class="flex cursor-pointer flex-col gap-1 rounded-lg border p-3 transition-colors"
                                :class="form.visibility === option.value ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                            >
                                <input v-model="form.visibility" type="radio" :value="option.value" class="sr-only" />
                                <span class="flex items-center gap-1.5 text-xs font-semibold"
                                    ><component :is="option.icon" class="size-3.5" /> {{ option.label }}</span
                                >
                                <span class="text-[11px] leading-snug text-muted-foreground">{{ option.description }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div v-else-if="step === 2" class="space-y-7">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <span class="label mb-0">Design template</span>
                            <span class="text-[11px] text-muted-foreground">Switch any time — content never changes.</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="theme in props.themes"
                                :key="theme.key"
                                class="group cursor-pointer overflow-hidden rounded-lg border transition-all"
                                :class="
                                    form.theme_key === theme.key ? 'border-primary ring-2 ring-primary/15' : 'border-border hover:border-primary/40'
                                "
                            >
                                <input v-model="form.theme_key" type="radio" :value="theme.key" class="sr-only" />
                                <div class="relative aspect-[16/9] overflow-hidden bg-secondary">
                                    <img
                                        :src="theme.preview"
                                        :alt="`${theme.name} preview`"
                                        class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                    />
                                    <span
                                        v-if="form.theme_key === theme.key"
                                        class="absolute right-2 top-2 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground shadow"
                                    >
                                        <Check class="size-3.5" />
                                    </span>
                                </div>
                                <div class="p-3">
                                    <p class="text-sm font-semibold">{{ theme.name }}</p>
                                    <p class="mt-1 line-clamp-2 text-[11px] leading-snug text-muted-foreground">{{ theme.description }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <span class="label">Cover layout</span>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label
                                v-for="option in [
                                    { value: 'hero', label: 'Full-width hero', hint: 'Title over imagery' },
                                    { value: 'editorial', label: 'Editorial', hint: 'Image with a note' },
                                    { value: 'cards', label: 'Card carousel', hint: 'Three visual cards' },
                                ]"
                                :key="option.value"
                                class="cursor-pointer rounded-lg border p-3"
                                :class="form.cover_style === option.value ? 'border-primary bg-primary/5' : 'border-border'"
                            >
                                <input v-model="form.cover_style" type="radio" :value="option.value" class="sr-only" />
                                <span class="block text-xs font-semibold">{{ option.label }}</span>
                                <span class="mt-0.5 block text-[11px] text-muted-foreground">{{ option.hint }}</span>
                            </label>
                        </div>
                        <p class="field-hint">You can add up to three cover images right after creation.</p>
                    </div>

                    <div class="border-t border-border pt-5">
                        <div class="mb-2">
                            <span class="label mb-1">Cross-references</span>
                            <p class="text-[11px] text-muted-foreground">
                                Link this work to articles or topics from other published encyclopedias. They appear as related works for readers and
                                are pinned into release snapshots.
                            </p>
                        </div>
                        <CrossReferencePicker v-model="form.references" />
                    </div>

                    <label class="flex items-start gap-3 rounded-lg border border-border p-3">
                        <input
                            v-model="form.export_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input text-primary focus:ring-primary"
                        />
                        <span>
                            <span class="block text-xs font-semibold">Allow readers to download the offline edition</span>
                            <span class="mt-0.5 block text-[11px] text-muted-foreground"
                                >A self-contained website archive of the latest release. You can disable this later.</span
                            >
                        </span>
                    </label>
                </div>

                <!-- Step 3 -->
                <div v-else class="space-y-5">
                    <div class="rounded-lg border border-border bg-secondary/40 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Identity</p>
                        <h2 class="font-display mt-2 text-2xl">{{ form.title || 'Untitled encyclopedia' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ form.subtitle || 'No subtitle' }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">{{ form.description || 'No description yet.' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                            <span class="chip">{{ form.language }}</span>
                            <span class="chip">{{ form.visibility }}</span>
                            <span class="chip">alarife.test/{{ form.slug || 'slug' }}</span>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border p-4">
                            <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                <LayoutTemplate class="size-3.5" /> Template
                            </p>
                            <p class="font-display mt-2 text-lg">{{ selectedTheme?.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ selectedTheme?.description }}</p>
                        </div>
                        <div class="rounded-lg border border-border p-4">
                            <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                <Image class="size-3.5" /> Covers
                            </p>
                            <p class="font-display mt-2 text-lg capitalize">{{ form.cover_style }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Add up to three images in the workspace. One image disables carousel arrows automatically.
                            </p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-border p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Cross-references</p>
                        <template v-if="form.references.length">
                            <ul class="mt-2 space-y-1">
                                <li
                                    v-for="reference in form.references"
                                    :key="reference.key ?? `${reference.target_type}:${reference.target_id}`"
                                    class="text-xs"
                                >
                                    <span class="font-medium">{{ reference.title }}</span>
                                    <span class="text-muted-foreground"> · {{ reference.encyclopedia_title }}</span>
                                </li>
                            </ul>
                        </template>
                        <p v-else class="mt-1 text-xs text-muted-foreground">
                            None selected. You can add related published works here or later in Settings.
                        </p>
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Creating the encyclopedia saves it as a draft. Add topics, write the first article, then publish a release when ready.
                    </p>
                </div>

                <div class="mt-7 flex items-center justify-between border-t border-border pt-5">
                    <button v-if="step > 1" class="btn-ghost" type="button" @click="back"><ArrowLeft class="size-4" /> Back</button>
                    <span v-else />
                    <button v-if="step < 3" class="btn-primary" type="button" @click="next">Continue <ArrowRight class="size-4" /></button>
                    <button v-else class="btn-primary" type="button" :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Creating…' : 'Create encyclopedia' }}
                        <ArrowRight v-if="!form.processing" class="size-4" />
                    </button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="surface overflow-hidden">
                    <div class="relative aspect-[16/9] bg-secondary">
                        <img v-if="selectedTheme" :src="selectedTheme.preview" alt="" class="size-full object-cover" />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                        <div class="absolute inset-x-0 bottom-0 p-4 text-white">
                            <p class="text-[9px] font-semibold uppercase tracking-[0.18em] opacity-80">{{ selectedTheme?.name }}</p>
                            <p class="font-display text-xl leading-tight">{{ form.title || 'Your encyclopedia' }}</p>
                        </div>
                    </div>
                    <div class="p-4 text-xs text-muted-foreground">
                        <p class="flex items-center justify-between">
                            <span>Slug</span><span class="font-mono text-foreground">/{{ form.slug || 'slug' }}</span>
                        </p>
                        <p class="mt-2 flex items-center justify-between">
                            <span>Visibility</span><span class="capitalize text-foreground">{{ form.visibility }}</span>
                        </p>
                        <p class="mt-2 flex items-center justify-between"><span>Status</span><span class="text-foreground">Draft</span></p>
                    </div>
                </div>
                <div class="rounded-xl border border-primary/20 bg-primary/[0.04] p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">What comes next</p>
                    <ol class="mt-2 space-y-2 text-xs leading-relaxed text-muted-foreground">
                        <li>1. Add covers and refine the design.</li>
                        <li>2. Create topics and write articles.</li>
                        <li>3. Publish a release and export the offline website.</li>
                    </ol>
                </div>
            </aside>
        </div>
    </AuthorLayout>
</template>
