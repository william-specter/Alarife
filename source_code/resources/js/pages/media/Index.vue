<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatBytes, formatDate } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Copy, Search, Trash2, UploadCloud } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface MediaItem {
    id: number;
    url: string;
    filename: string;
    alt_text: string | null;
    caption: string | null;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    created_at: string | null;
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string };
    media: {
        data: MediaItem[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q: string };
    canWrite: boolean;
}>();

const search = ref(props.filters.q);
const selectedId = ref<number | null>(props.media.data[0]?.id ?? null);
const selected = computed(() => props.media.data.find((item) => item.id === selectedId.value) ?? null);
const detail = ref({ alt_text: selected.value?.alt_text ?? '', caption: selected.value?.caption ?? '' });
const deleteOpen = ref(false);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const upload = useForm<{ file: File | null; alt_text: string; caption: string }>({ file: null, alt_text: '', caption: '' });

function select(item: MediaItem) {
    selectedId.value = item.id;
    detail.value = { alt_text: item.alt_text ?? '', caption: item.caption ?? '' };
}

function submitSearch() {
    router.get(route('author.media.index', props.encyclopedia.slug), search.value ? { q: search.value } : {}, { preserveState: true, replace: true });
}

function saveDetails() {
    if (!selected.value) return;
    router.put(route('author.media.update', [props.encyclopedia.slug, selected.value.id]), detail.value, { preserveScroll: true });
}

function setFile(file: File | null) {
    upload.file = file;
    upload.clearErrors();
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) setFile(file);
}

function submitUpload() {
    if (!upload.file) return;
    upload.post(route('author.media.store', props.encyclopedia.slug), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            upload.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function destroy() {
    if (!selected.value) return;
    router.delete(route('author.media.destroy', [props.encyclopedia.slug, selected.value.id]), {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

function copyUrl() {
    if (selected.value) navigator.clipboard?.writeText(selected.value.url);
}
</script>

<template>
    <Head :title="`Media · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="media" :breadcrumbs="[{ label: 'Media library' }]">
        <PageHeader
            eyebrow="Assets"
            title="Media library"
            description="Images are stored once and referenced by id, so changing themes or layouts never breaks a figure."
        >
            <template #actions>
                <button v-if="canWrite" class="btn-primary" type="button" @click="fileInput?.click()">
                    <UploadCloud class="size-4" /> Upload image
                </button>
            </template>
        </PageHeader>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">{{ media.total }} images</p>
                    <form class="relative" @submit.prevent="submitSearch">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" class="input pl-9 sm:w-60" placeholder="Search filename or alt text…" aria-label="Search media" />
                    </form>
                </div>

                <div v-if="media.data.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <button
                        v-for="item in media.data"
                        :key="item.id"
                        type="button"
                        class="group overflow-hidden rounded-lg border bg-card text-left transition-all"
                        :class="selectedId === item.id ? 'border-primary ring-2 ring-primary/15' : 'border-border hover:border-primary/40'"
                        @click="select(item)"
                    >
                        <img :src="item.url" :alt="item.alt_text ?? item.filename" class="aspect-square w-full object-cover" />
                        <span class="block truncate px-2 py-1.5 text-[10px] text-muted-foreground">{{ item.filename }}</span>
                    </button>
                </div>
                <EmptyState
                    v-else
                    title="No images yet"
                    description="Upload editorial imagery, diagrams and photographs. Covers and article figures share this library."
                >
                    <button v-if="canWrite" class="btn-primary" type="button" @click="fileInput?.click()">
                        <UploadCloud class="size-4" /> Upload the first image
                    </button>
                </EmptyState>

                <div v-if="media.data.length" class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                    <span>{{ media.from }}–{{ media.to }} of {{ media.total }}</span>
                    <div class="flex gap-1">
                        <template v-for="link in media.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded px-2 py-1 hover:bg-secondary"
                                :class="link.active ? 'bg-foreground text-background' : ''"
                                preserve-scroll
                                ><span v-html="link.label"
                            /></Link>
                            <span v-else class="rounded px-2 py-1 opacity-40" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <!-- Upload -->
                <div v-if="canWrite" class="surface p-4">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Upload</h2>
                    <div
                        class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed p-5 text-center"
                        :class="dragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                        role="button"
                        tabindex="0"
                        @click="fileInput?.click()"
                        @keydown.enter="fileInput?.click()"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <UploadCloud class="mb-1.5 size-5 text-muted-foreground" />
                        <p class="text-xs font-medium">{{ upload.file ? upload.file.name : 'Drop or browse' }}</p>
                        <p class="mt-0.5 text-[10px] text-muted-foreground">JPEG · PNG · WebP · AVIF · 10 MB</p>
                    </div>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/avif"
                        class="hidden"
                        @change="setFile(($event.target as HTMLInputElement).files?.[0] ?? null)"
                    />
                    <p v-if="upload.errors.file" class="field-error">{{ upload.errors.file }}</p>
                    <div class="mt-2 space-y-2">
                        <input v-model="upload.alt_text" class="input" placeholder="Alt text" aria-label="Alt text" />
                        <input v-model="upload.caption" class="input" placeholder="Caption (optional)" aria-label="Caption" />
                        <button class="btn-primary w-full" type="button" :disabled="!upload.file || upload.processing" @click="submitUpload">
                            {{ upload.processing ? 'Uploading…' : 'Upload' }}
                        </button>
                    </div>
                </div>

                <!-- Details -->
                <div v-if="selected" class="surface p-4">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-semibold">{{ selected.filename }}</h2>
                            <p class="text-[10px] text-muted-foreground">{{ formatDate(selected.created_at) }}</p>
                        </div>
                        <button class="btn-ghost size-7 p-0" type="button" title="Copy URL" @click="copyUrl"><Copy class="size-3.5" /></button>
                    </div>
                    <img :src="selected.url" :alt="selected.alt_text ?? selected.filename" class="mb-3 max-h-52 w-full rounded-md object-cover" />
                    <div class="mb-3 grid grid-cols-2 gap-2 text-[10px] text-muted-foreground">
                        <span>{{ selected.width }}×{{ selected.height }}</span>
                        <span class="text-right">{{ formatBytes(selected.size) }}</span>
                        <span>{{ selected.mime_type }}</span>
                        <span class="text-right">#{{ selected.id }}</span>
                    </div>
                    <div class="space-y-2">
                        <div>
                            <label class="label" for="media-alt">Alt text</label>
                            <input id="media-alt" v-model="detail.alt_text" class="input" />
                        </div>
                        <div>
                            <label class="label" for="media-caption">Caption</label>
                            <input id="media-caption" v-model="detail.caption" class="input" />
                        </div>
                        <button class="btn-secondary w-full" type="button" @click="saveDetails">Save details</button>
                        <button class="btn-ghost btn-sm w-full text-destructive" type="button" @click="deleteOpen = true">
                            <Trash2 class="size-3.5" /> Delete image
                        </button>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-border p-5 text-center text-xs text-muted-foreground">
                    Select an image to edit its accessible metadata.
                </div>
            </aside>
        </div>

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Delete this image?"
            description="Images used by a cover, featured image or article body cannot be deleted until those references are removed."
            confirm-label="Delete image"
            tone="danger"
            @confirm="destroy"
        />
    </AuthorLayout>
</template>
