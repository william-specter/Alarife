<script setup lang="ts">
import { BookOpen } from 'lucide-vue-next';

withDefaults(defineProps<{ title: string; description?: string; icon?: boolean }>(), {
    description: '',
    icon: true,
});
</script>

<template>
    <div class="surface flex flex-col items-center justify-center px-6 py-14 text-center">
        <span v-if="icon" class="mb-4 flex size-12 items-center justify-center rounded-full border border-border bg-secondary">
            <BookOpen class="size-5 text-muted-foreground" />
        </span>
        <h3 class="font-display text-xl">{{ title }}</h3>
        <p v-if="description" class="mt-2 max-w-md text-sm text-muted-foreground">{{ description }}</p>
        <div class="mt-5"><slot /></div>
    </div>
</template>
