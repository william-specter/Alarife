import { describe, expect, it } from 'vitest';
import { formatBytes, formatDate, pluralize, relativeTime, wordLabel } from './format';

describe('format helpers', () => {
    it('formats byte sizes for export history', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(2048)).toBe('2.0 KB');
        expect(formatBytes(13 * 1024 * 1024)).toBe('13 MB');
        expect(formatBytes(null)).toBe('—');
    });

    it('formats counts and abbreviations', () => {
        expect(pluralize(1, 'article')).toBe('1 article');
        expect(pluralize(3, 'article')).toBe('3 articles');
        expect(wordLabel(950)).toBe('950');
        expect(wordLabel(1250)).toBe('1.3k');
    });

    it('formats dates defensively', () => {
        expect(formatDate(null)).toBe('—');
        expect(formatDate('2026-11-16T10:00:00Z', { year: 'numeric' })).toContain('2026');
        expect(relativeTime(new Date(Date.now() - 5 * 60_000).toISOString())).toBe('5m ago');
    });
});
