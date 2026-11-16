<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { Check, CircleAlert, Info, X } from 'lucide-vue-next';

const { toasts, dismissToast } = useToast();

const tones = {
    success: {
        icon: Check,
        classes: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-100',
    },
    error: { icon: CircleAlert, classes: 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/60 dark:bg-red-950/50 dark:text-red-100' },
    info: { icon: Info, classes: 'border-border bg-card text-foreground' },
} as const;
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-[min(92vw,380px)] flex-col gap-2" aria-live="polite">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-1 opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-start gap-3 rounded-lg border p-3.5 shadow-lg shadow-black/5"
                :class="tones[toast.tone].classes"
            >
                <component :is="tones[toast.tone].icon" class="mt-0.5 size-4 shrink-0" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium leading-snug">{{ toast.title }}</p>
                    <p v-if="toast.description" class="mt-0.5 text-xs opacity-80">{{ toast.description }}</p>
                </div>
                <button type="button" class="rounded p-0.5 opacity-60 hover:opacity-100" aria-label="Dismiss" @click="dismissToast(toast.id)">
                    <X class="size-3.5" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
