<script setup lang="ts">
import type { CrossReferenceSelection } from '@/types/references';
import { Link2, Loader2, Plus, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { route } from 'ziggy-js';

type CrossReference = CrossReferenceSelection;

const props = withDefaults(
    defineProps<{
        modelValue: CrossReference[];
        excludeSlug?: string;
        disabled?: boolean;
    }>(),
    { excludeSlug: '', disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [value: CrossReference[]] }>();

const query = ref('');
const results = ref<CrossReference[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
let timer: number | null = null;
let controller: AbortController | null = null;

const selectedKeys = computed(() => new Set(props.modelValue.map((reference) => keyOf(reference))));

function keyOf(reference: CrossReference): string {
    return reference.key ?? `${reference.target_type}:${reference.target_id}`;
}

watch(query, (value) => {
    if (timer) window.clearTimeout(timer);
    if (value.trim().length < 2) {
        results.value = [];
        return;
    }
    timer = window.setTimeout(search, 320);
});

async function search() {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;

    try {
        const params = new URLSearchParams({ q: query.value.trim() });
        if (props.excludeSlug) params.set('exclude', props.excludeSlug);
        const response = await fetch(`${route('author.references.search')}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('Search failed. Try again.');
        const data = await response.json();
        results.value = (data.results as CrossReference[]).filter((result) => !selectedKeys.value.has(keyOf(result)));
    } catch (exception) {
        if ((exception as Error).name !== 'AbortError') {
            error.value = exception instanceof Error ? exception.message : 'Search failed.';
        }
    } finally {
        loading.value = false;
    }
}

function add(reference: CrossReference) {
    if (selectedKeys.value.has(keyOf(reference))) return;
    emit('update:modelValue', [
        ...props.modelValue,
        {
            key: keyOf(reference),
            target_type: reference.target_type,
            target_id: reference.target_id,
            target_encyclopedia_id: reference.target_encyclopedia_id,
            title: reference.title,
            encyclopedia_title: reference.encyclopedia_title,
            encyclopedia_slug: reference.encyclopedia_slug,
            excerpt: reference.excerpt,
            note: '',
        },
    ]);
    results.value = results.value.filter((result) => keyOf(result) !== keyOf(reference));
    query.value = '';
}

function remove(key: string) {
    emit(
        'update:modelValue',
        props.modelValue.filter((reference) => keyOf(reference) !== key),
    );
}

function updateNote(key: string, note: string) {
    emit(
        'update:modelValue',
        props.modelValue.map((reference) => (keyOf(reference) === key ? { ...reference, note } : reference)),
    );
}

onBeforeUnmount(() => {
    if (timer) window.clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div class="space-y-3">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <input
                v-model="query"
                class="input pl-9"
                placeholder="Search published articles and topics in other encyclopedias…"
                aria-label="Search cross-references"
                :disabled="disabled"
            />
            <Loader2 v-if="loading" class="absolute right-3 top-1/2 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground" />
        </div>

        <p class="text-[11px] text-muted-foreground">
            Only published, readable works appear here. References are pinned into each release and shown to readers as related works.
        </p>

        <div v-if="query.trim().length >= 2 && !loading" class="max-h-56 overflow-y-auto rounded-lg border border-border">
            <button
                v-for="result in results"
                :key="keyOf(result)"
                type="button"
                class="flex w-full items-start gap-3 border-b border-border px-3 py-2.5 text-left transition-colors last:border-0 hover:bg-secondary"
                @click="add(result)"
            >
                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md border border-border bg-secondary/50">
                    <Link2 class="size-3.5 text-muted-foreground" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-medium">{{ result.title }}</span>
                    <span class="block truncate text-[10px] text-muted-foreground">
                        {{ result.target_type === 'article' ? 'Article' : 'Topic' }} · {{ result.encyclopedia_title }}
                    </span>
                    <span v-if="result.excerpt" class="mt-0.5 line-clamp-1 block text-[10px] text-muted-foreground/80">{{ result.excerpt }}</span>
                </span>
                <Plus class="mt-1 size-3.5 shrink-0 text-primary" />
            </button>
            <p v-if="!results.length" class="px-3 py-4 text-center text-[11px] text-muted-foreground">No published matches. Try another title.</p>
        </div>
        <p v-if="error" class="field-error">{{ error }}</p>

        <div v-if="modelValue.length" class="space-y-2">
            <div class="flex items-center justify-between">
                <p class="label mb-0">Selected references</p>
                <span class="text-[10px] text-muted-foreground">{{ modelValue.length }} / 50</span>
            </div>
            <div v-for="reference in modelValue" :key="keyOf(reference)" class="rounded-lg border border-border bg-secondary/30 p-3">
                <div class="flex items-start gap-2">
                    <span
                        class="mt-0.5 rounded border border-border bg-card px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                    >
                        {{ reference.target_type }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold">{{ reference.title }}</p>
                        <p class="truncate text-[10px] text-muted-foreground">{{ reference.encyclopedia_title }}</p>
                        <p v-if="reference.available === false" class="mt-1 text-[10px] font-medium text-destructive">
                            Target is no longer published — remove it before saving.
                        </p>
                    </div>
                    <button
                        class="rounded p-1 text-muted-foreground hover:text-destructive"
                        type="button"
                        aria-label="Remove reference"
                        @click="remove(keyOf(reference))"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
                <input
                    :value="reference.note ?? ''"
                    class="input mt-2 h-7 text-[11px]"
                    placeholder="Optional note shown beside this reference"
                    maxlength="300"
                    :disabled="disabled"
                    @input="updateNote(keyOf(reference), ($event.target as HTMLInputElement).value)"
                />
            </div>
        </div>
        <p v-else class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-[11px] text-muted-foreground">
            No cross-references selected yet.
        </p>
    </div>
</template>
