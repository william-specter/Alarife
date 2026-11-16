<script setup lang="ts">
withDefaults(defineProps<{ label: string; value: string | number; hint?: string; accent?: boolean }>(), {
    hint: '',
    accent: false,
});
</script>

<template>
    <div class="surface p-4">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">{{ label }}</p>
        <p class="stat-value mt-3" :class="accent ? 'text-primary' : ''">{{ value }}</p>
        <p v-if="hint" class="mt-2 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
