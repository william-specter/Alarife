<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import CrossReferencePicker from '@/components/CrossReferencePicker.vue';
import PageHeader from '@/components/PageHeader.vue';
import type { CrossReferenceSelection } from '@/types/references';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
        language: string;
        cover_style: string;
        theme_name: string | null;
        export_enabled: boolean;
    };
    role: string | null;
    canManage: boolean;
    references: CrossReferenceSelection[];
    languages: { code: string; label: string }[];
}>();

const form = useForm({
    title: props.encyclopedia.title,
    subtitle: props.encyclopedia.subtitle ?? '',
    slug: props.encyclopedia.slug,
    description: props.encyclopedia.description ?? '',
    language: props.encyclopedia.language,
    visibility: props.encyclopedia.visibility,
    cover_style: props.encyclopedia.cover_style,
    export_enabled: props.encyclopedia.export_enabled,
    references: props.references as CrossReferenceSelection[],
});

const confirmDelete = ref(false);
const confirmDuplicate = ref(false);

function save() {
    form.put(route('author.settings.update', props.encyclopedia.slug), { preserveScroll: true });
}

function destroy() {
    router.delete(route('author.encyclopedias.destroy', props.encyclopedia.slug));
}

function duplicate() {
    router.post(route('author.encyclopedias.duplicate', props.encyclopedia.slug));
}
</script>

<template>
    <Head :title="`Settings · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="settings" :breadcrumbs="[{ label: 'Settings' }]">
        <PageHeader
            eyebrow="Encyclopedia"
            title="Settings"
            description="Identity, access and distribution. Changes to the slug break existing links."
        />

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="save">
            <div class="space-y-6">
                <section class="surface p-5">
                    <h2 class="text-sm font-semibold">Identity</h2>
                    <p class="mb-4 text-xs text-muted-foreground">How the reference work presents itself.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="label" for="title">Title</label>
                            <input id="title" v-model="form.title" class="input" :disabled="!canManage" />
                            <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="subtitle">Subtitle</label>
                                <input id="subtitle" v-model="form.subtitle" class="input" :disabled="!canManage" />
                                <p v-if="form.errors.subtitle" class="field-error">{{ form.errors.subtitle }}</p>
                            </div>
                            <div>
                                <label class="label" for="language">Language</label>
                                <select id="language" v-model="form.language" class="input" :disabled="!canManage">
                                    <option v-for="language in props.languages" :key="language.code" :value="language.code">
                                        {{ language.label }}
                                    </option>
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
                                    :disabled="!canManage"
                                />
                            </div>
                            <p v-if="form.errors.slug" class="field-error">{{ form.errors.slug }}</p>
                            <p v-else class="field-hint">Changing a published slug leaves old links unresolved. Plan redirects before renaming.</p>
                        </div>
                        <div>
                            <label class="label" for="description">Description</label>
                            <textarea id="description" v-model="form.description" rows="4" class="textarea" :disabled="!canManage" />
                            <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </section>

                <section class="surface p-5">
                    <h2 class="text-sm font-semibold">Access & distribution</h2>
                    <p class="mb-4 text-xs text-muted-foreground">Controls who can read and download this encyclopedia.</p>
                    <div class="space-y-4">
                        <div>
                            <span class="label">Visibility</span>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="option in [
                                        { value: 'private', label: 'Private', hint: 'Members only' },
                                        { value: 'unlisted', label: 'Unlisted', hint: 'Anyone with link' },
                                        { value: 'public', label: 'Public', hint: 'Listed and indexable' },
                                    ]"
                                    :key="option.value"
                                    class="cursor-pointer rounded-lg border p-3"
                                    :class="form.visibility === option.value ? 'border-primary bg-primary/5' : 'border-border'"
                                >
                                    <input v-model="form.visibility" type="radio" :value="option.value" class="sr-only" :disabled="!canManage" />
                                    <span class="block text-xs font-semibold">{{ option.label }}</span>
                                    <span class="text-[11px] text-muted-foreground">{{ option.hint }}</span>
                                </label>
                            </div>
                        </div>
                        <label class="flex items-start gap-3 rounded-lg border border-border p-3">
                            <input
                                v-model="form.export_enabled"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input text-primary focus:ring-primary"
                                :disabled="!canManage"
                            />
                            <span>
                                <span class="block text-xs font-semibold">Allow public downloads</span>
                                <span class="mt-0.5 block text-[11px] text-muted-foreground"
                                    >Readers can download the current release as a static website.</span
                                >
                            </span>
                        </label>
                        <div>
                            <span class="label">Cover layout</span>
                            <select v-model="form.cover_style" class="input" :disabled="!canManage">
                                <option value="hero">Full-width hero</option>
                                <option value="editorial">Editorial carousel</option>
                                <option value="cards">Card carousel</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="surface p-5">
                    <h2 class="text-sm font-semibold">Cross-references</h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Point readers to published articles and topics in other encyclopedias. References are displayed as related works and captured
                        in every release.
                    </p>
                    <CrossReferencePicker v-model="form.references" :exclude-slug="encyclopedia.slug" :disabled="!canManage" />
                    <p v-if="(form.errors as any).references" class="field-error">{{ (form.errors as any).references }}</p>
                </section>

                <div class="flex justify-end">
                    <button class="btn-primary" type="submit" :disabled="form.processing || !canManage">
                        {{ form.processing ? 'Saving…' : 'Save settings' }}
                    </button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="surface p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">At a glance</p>
                    <dl class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Status</dt>
                            <dd class="capitalize">{{ encyclopedia.status }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Theme</dt>
                            <dd>{{ encyclopedia.theme_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Your role</dt>
                            <dd class="capitalize">{{ role ?? 'Member' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Path</dt>
                            <dd class="font-mono text-[11px]">/{{ encyclopedia.slug }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="canManage" class="surface p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Duplicate</p>
                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                        Copy topics, articles, covers and theme into a new private draft.
                    </p>
                    <button class="btn-secondary btn-sm mt-3 w-full" type="button" @click="confirmDuplicate = true">
                        <Copy class="size-3.5" /> Duplicate encyclopedia
                    </button>
                </div>

                <div v-if="canManage" class="rounded-xl border border-destructive/25 bg-destructive/[0.03] p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-destructive">Danger zone</p>
                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                        Soft delete removes the encyclopedia from every surface. Releases and downloaded archives remain valid.
                    </p>
                    <button class="btn-danger btn-sm mt-3 w-full" type="button" @click="confirmDelete = true">
                        <Trash2 class="size-3.5" /> Delete encyclopedia
                    </button>
                </div>
            </aside>
        </form>

        <ConfirmDialog
            v-model:open="confirmDelete"
            title="Delete this encyclopedia?"
            :description="`“${encyclopedia.title}” and its workspace will disappear. This cannot be undone from the UI.`"
            confirm-label="Delete permanently"
            tone="danger"
            @confirm="destroy"
        />
        <ConfirmDialog
            v-model:open="confirmDuplicate"
            title="Duplicate this encyclopedia?"
            description="A private draft copy will be created with its own covers, topics and articles."
            confirm-label="Create copy"
            @confirm="duplicate"
        />
    </AuthorLayout>
</template>
