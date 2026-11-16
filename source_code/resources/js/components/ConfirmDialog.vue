<script setup lang="ts">
defineProps<{
    open: boolean;
    title: string;
    description?: string;
    confirmLabel?: string;
    tone?: 'danger' | 'primary';
}>();
const emit = defineEmits<{ 'update:open': [value: boolean]; confirm: [] }>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/45 p-4" @click.self="emit('update:open', false)">
                <div
                    class="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                >
                    <h2 class="font-display text-xl">{{ title }}</h2>
                    <p v-if="description" class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ description }}</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button class="btn-secondary" type="button" @click="emit('update:open', false)">Cancel</button>
                        <button :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'" type="button" @click="emit('confirm')">
                            {{ confirmLabel ?? 'Confirm' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
