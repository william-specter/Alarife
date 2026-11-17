import { getEcho } from '@/echo';
import { onBeforeUnmount, onMounted } from 'vue';

export interface RealtimeEvent {
    type: string;
    payload: Record<string, any>;
    occurred_at: string;
    encyclopedia_id?: number;
}

export function useEncyclopediaRealtime(encyclopediaId: number | string | null | undefined, handler: (event: RealtimeEvent) => void) {
    let channel: any = null;
    const name = encyclopediaId ? `encyclopedia.${encyclopediaId}` : null;

    onMounted(() => {
        const echo = getEcho();
        if (!echo || !name) return;

        channel = echo.private(name);
        channel.listen('.encyclopedia.updated', handler);
    });

    onBeforeUnmount(() => {
        if (!channel || !name) return;
        channel.stopListening('.encyclopedia.updated');
        getEcho()?.leave(name);
        channel = null;
    });
}

export function useUserRealtime(userId: number | string | null | undefined, handler: (event: RealtimeEvent) => void) {
    let channel: any = null;
    const name = userId ? `user.${userId}` : null;

    onMounted(() => {
        const echo = getEcho();
        if (!echo || !name) return;

        channel = echo.private(name);
        channel.listen('.user.notified', handler);
    });

    onBeforeUnmount(() => {
        if (!channel || !name) return;
        channel.stopListening('.user.notified');
        getEcho()?.leave(name);
        channel = null;
    });
}
