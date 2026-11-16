<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, ImagePlus, Library, Trash2, UploadCloud } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Media {
    id: number;
    url: string;
    filename?: string;
    alt_text?: string | null;
    width?: number | null;
    height?: number | null;
}

interface Cover {
    id: number;
    position: number;
    caption: string | null;
    alt_text: string | null;
    media: Media;
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string; cover_style: string };
    covers: Cover[];
    library: Media[];
    canManage: boolean;
    maxCovers: number;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const dragging = ref(false);
const libraryOpen = ref(false);
const confirmRemove = ref<number | null>(null);

const upload = useForm<{ file: File | null; caption: string; alt_text: string }>({
    file: null,
    caption: '',
    alt_text: '',
});

const captionDraft = ref<Record<number, { caption: string; alt_text: string }>>(
    Object.fromEntries(props.covers.map((cover) => [cover.id, { caption: cover.caption ?? '', alt_text: cover.alt_text ?? '' }])),
);

const canAdd = computed(() => props.canManage && props.covers.length < props.maxCovers);
const slots = computed(() => Array.from({ length: props.maxCovers }, (_, index) => props.covers[index] ?? null));

function pickFile() {
    fileInput.value?.click();
}

function onFile(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    setFile(file);
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) setFile(file);
}

function setFile(file: File | null) {
    upload.file = file;
    upload.clearErrors();
    preview.value = file ? URL.createObjectURL(file) : null;
}

function submitUpload() {
    if (!upload.file) return;
    upload.post(route('author.covers.store', props.encyclopedia.slug), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            upload.reset();
            preview.value = null;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function addFromLibrary(media: Media) {
    router.post(
        route('author.covers.store', props.encyclopedia.slug),
        { media_asset_id: media.id },
        { preserveScroll: true, onSuccess: () => (libraryOpen.value = false) },
    );
}

function saveCover(cover: Cover) {
    router.put(route('author.covers.update', [props.encyclopedia.slug, cover.id]), captionDraft.value[cover.id], { preserveScroll: true });
}

function removeCover(id: number) {
    router.delete(route('author.covers.destroy', [props.encyclopedia.slug, id]), {
        preserveScroll: true,
        onFinish: () => (confirmRemove.value = null),
    });
}

function confirmRemoval() {
    if (confirmRemove.value !== null) removeCover(confirmRemove.value);
}

function move(index: number, direction: -1 | 1) {
    const cover = slots.value[index];
    const swap = slots.value[index + direction];
    if (!cover || !swap) return;
    const order = slots.value.filter(Boolean).map((item) => item!.id);
    const from = order.indexOf(cover.id);
    const to = order.indexOf(swap.id);
    [order[from], order[to]] = [order[to], order[from]];
    router.put(route('author.covers.reorder', props.encyclopedia.slug), { order }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Covers · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="covers" :breadcrumbs="[{ label: 'Covers' }]">
        <PageHeader
            eyebrow="Visual identity"
            title="Cover images"
            description="Up to three images. The first cover leads the homepage; one image hides carousel controls automatically."
        >
            <template #actions>
                <a :href="route('author.preview', encyclopedia.slug)" target="_blank" rel="noopener" class="btn-secondary">Preview</a>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="(cover, index) in slots" :key="index" class="surface overflow-hidden">
                <template v-if="cover">
                    <div class="relative aspect-[4/3] bg-secondary">
                        <img :src="cover.media.url" :alt="cover.alt_text ?? cover.media.alt_text ?? ''" class="size-full object-cover" />
                        <div
                            class="absolute left-3 top-3 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-white backdrop-blur"
                        >
                            Cover {{ index + 1 }}{{ index === 0 ? ' · lead' : '' }}
                        </div>
                    </div>
                    <div class="space-y-3 p-4">
                        <div>
                            <label class="label">Caption</label>
                            <input v-model="captionDraft[cover.id].caption" class="input" placeholder="A short caption" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="label">Alt text</label>
                            <input v-model="captionDraft[cover.id].alt_text" class="input" placeholder="Describe the image" :disabled="!canManage" />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button class="btn-secondary btn-sm" type="button" :disabled="!canManage" @click="saveCover(cover)">Save</button>
                            <button
                                class="btn-ghost btn-sm size-8 p-0"
                                type="button"
                                :disabled="index === 0 || !canManage"
                                aria-label="Move cover left"
                                @click="move(index, -1)"
                            >
                                <ArrowLeft class="size-3.5" />
                            </button>
                            <button
                                class="btn-ghost btn-sm size-8 p-0"
                                type="button"
                                :disabled="index === props.covers.length - 1 || !canManage"
                                aria-label="Move cover right"
                                @click="move(index, 1)"
                            >
                                <ArrowRight class="size-3.5" />
                            </button>
                            <button
                                class="btn-ghost btn-sm ml-auto size-8 p-0 text-destructive"
                                type="button"
                                :disabled="!canManage"
                                aria-label="Remove cover"
                                @click="confirmRemove = cover.id"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </template>

                <div
                    v-else-if="canAdd"
                    class="m-3 flex h-full min-h-[280px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border p-6 text-center"
                >
                    <span class="flex size-10 items-center justify-center rounded-full border border-border bg-secondary"
                        ><ImagePlus class="size-4 text-muted-foreground"
                    /></span>
                    <p class="text-sm font-medium">Empty cover slot</p>
                    <p class="max-w-[200px] text-[11px] text-muted-foreground">Upload an image or choose one from the library.</p>
                    <div class="mt-1 flex gap-1.5">
                        <button class="btn-secondary btn-sm" type="button" @click="pickFile"><UploadCloud class="size-3.5" /> Upload</button>
                        <button class="btn-ghost btn-sm" type="button" @click="libraryOpen = true"><Library class="size-3.5" /> Library</button>
                    </div>
                </div>
                <div v-else class="m-3 flex h-full min-h-[280px] items-center justify-center p-6 text-center text-xs text-muted-foreground">
                    Cover slot {{ index + 1 }} — remove a cover to use it.
                </div>
            </div>
        </div>

        <!-- Upload panel -->
        <section v-if="canAdd" class="surface mt-6 p-5">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div>
                    <h2 class="text-sm font-semibold">Add a cover</h2>
                    <p class="text-[11px] text-muted-foreground">JPEG, PNG, WebP or AVIF · 10 MB max · at least 200×200 px.</p>
                    <div
                        class="mt-3 flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center transition-colors"
                        :class="dragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                        role="button"
                        tabindex="0"
                        @click="pickFile"
                        @keydown.enter="pickFile"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <img v-if="preview" :src="preview" alt="Selected cover preview" class="mb-3 max-h-32 rounded object-cover" />
                        <UploadCloud v-else class="mb-2 size-6 text-muted-foreground" />
                        <p class="text-sm font-medium">{{ preview ? 'Image selected' : 'Drop an image here or click to browse' }}</p>
                        <p class="mt-1 text-[11px] text-muted-foreground">A wide 16:9 image works best.</p>
                    </div>
                    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="hidden" @change="onFile" />
                    <p v-if="upload.errors.file" class="field-error">{{ upload.errors.file }}</p>
                    <p v-if="(upload.errors as any).covers" class="field-error">{{ (upload.errors as any).covers }}</p>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="label" for="cover-caption">Caption</label>
                        <input id="cover-caption" v-model="upload.caption" class="input" placeholder="Shown on the cover story" />
                    </div>
                    <div>
                        <label class="label" for="cover-alt">Alt text</label>
                        <input id="cover-alt" v-model="upload.alt_text" class="input" placeholder="Describe the image for screen readers" />
                    </div>
                    <button class="btn-primary w-full" type="button" :disabled="!upload.file || upload.processing" @click="submitUpload">
                        {{ upload.processing ? 'Uploading…' : 'Upload cover' }}
                    </button>
                    <button class="btn-secondary w-full" type="button" @click="libraryOpen = true">
                        <Library class="size-4" /> Choose from library
                    </button>
                </div>
            </div>
        </section>

        <!-- Library modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="libraryOpen"
                    class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/45 p-4 py-10"
                    @click.self="libraryOpen = false"
                >
                    <div class="w-full max-w-3xl rounded-xl border border-border bg-card shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border px-5 py-4">
                            <div>
                                <h2 class="font-display text-xl">Media library</h2>
                                <p class="text-xs text-muted-foreground">Choose an existing image. It must belong to this encyclopedia.</p>
                            </div>
                            <button class="btn-ghost size-8 p-0" type="button" aria-label="Close" @click="libraryOpen = false">✕</button>
                        </div>
                        <div v-if="library.length" class="grid max-h-[60vh] grid-cols-2 gap-2 overflow-y-auto p-4 sm:grid-cols-3 md:grid-cols-4">
                            <button
                                v-for="media in library"
                                :key="media.id"
                                type="button"
                                class="group overflow-hidden rounded-lg border border-border text-left hover:border-primary"
                                @click="addFromLibrary(media)"
                            >
                                <img :src="media.url" :alt="media.alt_text ?? media.filename ?? ''" class="aspect-square w-full object-cover" />
                                <span class="block truncate px-2 py-1.5 text-[10px] text-muted-foreground">{{ media.filename }}</span>
                            </button>
                        </div>
                        <p v-else class="p-10 text-center text-sm text-muted-foreground">No images yet. Upload one above.</p>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ConfirmDialog
            v-if="confirmRemove !== null"
            :open="confirmRemove !== null"
            title="Remove this cover?"
            description="The image stays in the media library; only the cover assignment is removed."
            confirm-label="Remove cover"
            tone="danger"
            @update:open="confirmRemove = null"
            @confirm="confirmRemoval"
        />
    </AuthorLayout>
</template>
