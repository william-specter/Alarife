import { ref } from 'vue';

export type ToastTone = 'success' | 'error' | 'info';

export interface Toast {
    id: number;
    tone: ToastTone;
    title: string;
    description?: string;
}

const toasts = ref<Toast[]>([]);
let counter = 0;

export function pushToast(tone: ToastTone, title: string, description?: string) {
    const toast: Toast = { id: ++counter, tone, title, description };
    toasts.value.push(toast);
    window.setTimeout(() => dismissToast(toast.id), 4800);
}

export function dismissToast(id: number) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

export function useToast() {
    return { toasts, pushToast, dismissToast };
}
