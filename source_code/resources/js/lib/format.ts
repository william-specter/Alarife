export function formatDate(value?: string | null, options?: Intl.DateTimeFormatOptions): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('en', options ?? { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

export function formatDateTime(value?: string | null): string {
    return formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function relativeTime(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    const diff = Date.now() - date.getTime();
    const minutes = Math.round(diff / 60000);

    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.round(hours / 24);
    if (days < 30) return `${days}d ago`;

    return formatDate(value);
}

export function formatBytes(value?: number | null): string {
    if (!value) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let index = 0;
    let size = value;
    while (size >= 1024 && index < units.length - 1) {
        size /= 1024;
        index++;
    }

    return `${size.toFixed(size >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
}

export function pluralize(count: number, singular: string, plural?: string): string {
    return `${count} ${count === 1 ? singular : (plural ?? `${singular}s`)}`;
}

export function wordLabel(count: number): string {
    if (count >= 1000) return `${(count / 1000).toFixed(1)}k`;
    return String(count);
}
