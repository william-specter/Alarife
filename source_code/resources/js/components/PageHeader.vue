<script setup lang="ts">
withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        description?: string;
    }>(),
    { eyebrow: '', description: '' },
);
</script>

<template>
    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary">{{ eyebrow }}</p>
            <h1 class="font-display text-[2rem] leading-[1.05] tracking-tight sm:text-[2.4rem]">{{ title }}</h1>
            <p v-if="description" class="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">{{ description }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
