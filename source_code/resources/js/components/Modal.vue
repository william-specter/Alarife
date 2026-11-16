<script setup lang="ts">
withDefaults(defineProps<{ open: boolean; title: string; description?: string; wide?: boolean }>(), {
    description: '',
    wide: false,
});
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/45 p-4 py-10"
                @click.self="emit('update:open', false)"
            >
                <div
                    class="w-full rounded-xl border border-border bg-card shadow-2xl"
                    :class="wide ? 'max-w-4xl' : 'max-w-lg'"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                >
                    <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
                        <div>
                            <h2 class="font-display text-xl">{{ title }}</h2>
                            <p v-if="description" class="mt-1 text-xs text-muted-foreground">{{ description }}</p>
                        </div>
                        <button class="btn-ghost size-8 p-0" type="button" aria-label="Close" @click="emit('update:open', false)">✕</button>
                    </div>
                    <div class="px-6 py-5"><slot /></div>
                    <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-border px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
