<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { UploadCloud } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface MediaItem {
    id: number;
    url: string;
    filename?: string;
    alt_text?: string | null;
    caption?: string | null;
    width?: number | null;
    height?: number | null;
}

const props = defineProps<{
    open: boolean;
    media: MediaItem[];
    mode: 'image' | 'gallery';
    uploadUrl: string;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean]; select: [items: MediaItem[]] }>();

const query = ref('');
const chosen = ref<number[]>([]);
const altText = ref('');
const uploading = ref(false);
const uploadError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const localMedia = ref<MediaItem[]>([]);

watch(
    () => props.open,
    (open) => {
        if (open) {
            query.value = '';
            chosen.value = [];
            altText.value = '';
            uploadError.value = null;
        }
    },
);

const allMedia = computed(() => [...localMedia.value, ...props.media]);
const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) return allMedia.value;
    return allMedia.value.filter((item) => `${item.filename ?? ''} ${item.alt_text ?? ''}`.toLowerCase().includes(needle));
});

function toggle(item: MediaItem) {
    if (props.mode === 'image') {
        chosen.value = [item.id];
        altText.value = item.alt_text ?? '';
        return;
    }
    chosen.value = chosen.value.includes(item.id) ? chosen.value.filter((id) => id !== item.id) : [...chosen.value, item.id];
}

function confirm() {
    const selected = allMedia.value.filter((item) => chosen.value.includes(item.id));
    if (!selected.length) return;

    if (props.mode === 'image') {
        emit('select', [{ ...selected[0], alt_text: altText.value || selected[0].alt_text }]);
    } else {
        emit('select', selected);
    }
    emit('update:open', false);
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function upload(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = null;
    const body = new FormData();
    body.append('file', file);

    try {
        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
            body,
        });
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message ?? 'Upload failed.');
        }
        const data = await response.json();
        const media: MediaItem = data.media;
        localMedia.value = [media, ...localMedia.value];
        chosen.value = [media.id];
        altText.value = media.alt_text ?? '';
    } catch (error) {
        uploadError.value = error instanceof Error ? error.message : 'Upload failed.';
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}
</script>

<template>
    <Modal
        :open="open"
        :title="mode === 'image' ? 'Insert an image' : 'Build a gallery'"
        :description="mode === 'image' ? 'Choose a library image or upload a new one.' : 'Select two or more images to arrange in a gallery.'"
        wide
        @update:open="emit('update:open', $event)"
    >
        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <input v-model="query" class="input" placeholder="Search by filename or alt text…" aria-label="Search media" />
            <button class="btn-secondary shrink-0" type="button" @click="fileInput?.click()">
                <UploadCloud class="size-4" /> {{ uploading ? 'Uploading…' : 'Upload' }}
            </button>
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="hidden" @change="upload" />
        </div>
        <p v-if="uploadError" class="field-error mb-3">{{ uploadError }}</p>

        <div v-if="filtered.length" class="grid max-h-[45vh] grid-cols-2 gap-2 overflow-y-auto pr-1 sm:grid-cols-3 md:grid-cols-4">
            <button
                v-for="item in filtered"
                :key="item.id"
                type="button"
                class="group relative overflow-hidden rounded-lg border text-left transition-all"
                :class="chosen.includes(item.id) ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:border-primary/50'"
                @click="toggle(item)"
            >
                <img :src="item.url" :alt="item.alt_text ?? item.filename ?? ''" class="aspect-square w-full object-cover" />
                <span
                    class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/70 to-transparent px-2 pb-1 pt-5 text-[10px] text-white"
                    >{{ item.filename }}</span
                >
                <span
                    v-if="chosen.includes(item.id)"
                    class="absolute right-1.5 top-1.5 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                >
                    {{ mode === 'gallery' ? chosen.indexOf(item.id) + 1 : '✓' }}
                </span>
            </button>
        </div>
        <p v-else class="py-10 text-center text-sm text-muted-foreground">No images found. Upload one to get started.</p>

        <div v-if="mode === 'image'" class="mt-4">
            <label class="label" for="picker-alt">Alt text</label>
            <input id="picker-alt" v-model="altText" class="input" placeholder="Describe the image for screen readers" />
        </div>

        <template #footer>
            <span class="mr-auto self-center text-[11px] text-muted-foreground">{{ chosen.length }} selected</span>
            <button class="btn-secondary" type="button" @click="emit('update:open', false)">Cancel</button>
            <button class="btn-primary" type="button" :disabled="!chosen.length" @click="confirm">
                {{ mode === 'image' ? 'Insert image' : 'Insert gallery' }}
            </button>
        </template>
    </Modal>
</template>
