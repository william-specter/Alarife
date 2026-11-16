<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: string;
}>();

const map: Record<string, { label: string; class: string; dot: string }> = {
    published: { label: 'Published', class: 'badge-published', dot: 'bg-emerald-500' },
    draft: { label: 'Draft', class: 'badge-draft', dot: 'bg-stone-400' },
    in_review: { label: 'In review', class: 'badge-review', dot: 'bg-amber-500' },
    archived: { label: 'Archived', class: 'badge-archived', dot: 'bg-stone-400' },
    active: { label: 'Live', class: 'badge-published', dot: 'bg-emerald-500' },
    private: { label: 'Private', class: 'badge-draft', dot: 'bg-stone-400' },
    unlisted: { label: 'Unlisted', class: 'badge-review', dot: 'bg-amber-500' },
    public: { label: 'Public', class: 'badge-published', dot: 'bg-emerald-500' },
    queued: { label: 'Queued', class: 'badge-draft', dot: 'bg-stone-400' },
    processing: { label: 'Processing', class: 'badge-review', dot: 'bg-amber-500' },
    completed: { label: 'Completed', class: 'badge-published', dot: 'bg-emerald-500' },
    failed: { label: 'Failed', class: 'badge-archived', dot: 'bg-red-500' },
};

const config = computed(() => map[props.value] ?? { label: props.value, class: 'badge-draft', dot: 'bg-stone-400' });
</script>

<template>
    <span :class="config.class">
        <span class="size-1.5 rounded-full" :class="config.dot" aria-hidden="true" />
        {{ config.label }}
    </span>
</template>
