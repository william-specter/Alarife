import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let instance: Echo<any> | null | undefined;

function meta(name: string): string | null {
    return document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`)?.content ?? null;
}

/**
 * Reverb is the default broadcast transport. Redis is not required.
 * Returns null when no Reverb key is configured, so the UI degrades to polling.
 */
export function getEcho(): Echo<any> | null {
    if (typeof window === 'undefined') return null;
    if (instance !== undefined) return instance;

    const key = meta('reverb-key');
    if (!key) {
        instance = null;
        return null;
    }

    const port = Number(meta('reverb-port') ?? 8080);
    const secure = window.location.protocol === 'https:';

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    instance = new Echo<any>({
        broadcaster: 'reverb',
        key,
        cluster: 'mt1',
        wsHost: window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: secure ? ['wss'] : ['ws'],
        disableStats: true,
    });

    return instance;
}
