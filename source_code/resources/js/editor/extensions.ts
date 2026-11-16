import { Node, mergeAttributes } from '@tiptap/core';
import Image from '@tiptap/extension-image';

export const FigureImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            mediaId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-media-id'),
                renderHTML: (attributes) => (attributes.mediaId ? { 'data-media-id': attributes.mediaId } : {}),
            },
            caption: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-caption'),
                renderHTML: (attributes) => (attributes.caption ? { 'data-caption': attributes.caption } : {}),
            },
        };
    },
    renderHTML({ HTMLAttributes }) {
        return ['img', mergeAttributes(HTMLAttributes)];
    },
});

export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return {
            tone: { default: 'note' },
            title: { default: '' },
        };
    },
    parseHTML() {
        return [{ tag: 'aside[data-callout]' }];
    },
    renderHTML({ HTMLAttributes }) {
        const { tone, title } = HTMLAttributes;
        return [
            'aside',
            { class: 'editor-callout', 'data-callout': '', 'data-tone': tone ?? 'note' },
            ['p', { class: 'editor-callout__title' }, title || tone || 'note'],
            ['div', { class: 'editor-callout__body' }, 0],
        ];
    },
});

export const Gallery = Node.create({
    name: 'gallery',
    group: 'block',
    atom: true,
    selectable: true,
    addAttributes() {
        return {
            layout: { default: 'grid' },
            images: { default: [] },
        };
    },
    parseHTML() {
        return [{ tag: 'div[data-gallery]' }];
    },
    renderHTML({ HTMLAttributes }) {
        const images = Array.isArray(HTMLAttributes.images) ? HTMLAttributes.images : [];
        return ['div', { class: 'editor-gallery', 'data-gallery': '' }, images.length + ' images in gallery'];
    },
    addNodeView() {
        return ({ node }: any) => {
            const dom = document.createElement('div');
            dom.className = 'editor-gallery';
            const images = Array.isArray(node.attrs.images) ? node.attrs.images : [];
            images.forEach((image: any) => {
                if (!image?.src) return;
                const figure = document.createElement('figure');
                const img = document.createElement('img');
                img.src = image.src;
                img.alt = image.alt ?? '';
                img.loading = 'lazy';
                figure.appendChild(img);
                if (image.caption) {
                    const caption = document.createElement('figcaption');
                    caption.textContent = image.caption;
                    figure.appendChild(caption);
                }
                dom.appendChild(figure);
            });
            if (!images.length) {
                dom.textContent = 'Empty gallery';
            }
            return { dom };
        };
    },
    addCommands() {
        return {
            insertGallery:
                (attrs: { images: { mediaId: number; src: string; alt?: string; caption?: string }[]; layout?: string }) =>
                ({ commands }: any) =>
                    commands.insertContent({ type: this.name, attrs: { images: attrs.images, layout: attrs.layout ?? 'grid' } }),
        } as any;
    },
});

export const Citation = Node.create({
    name: 'citation',
    group: 'block',
    atom: true,
    addAttributes() {
        return {
            source: { default: '' },
            url: { default: '' },
            accessed: { default: '' },
        };
    },
    parseHTML() {
        return [{ tag: 'p[data-citation]' }];
    },
    renderHTML({ HTMLAttributes }) {
        const { source, url, accessed } = HTMLAttributes;
        const parts: any[] = [['span', { class: 'editor-citation__label' }, 'Source '], source];
        if (url) parts.push(' — ', ['a', { href: url }, url]);
        if (accessed) parts.push(' (accessed ', accessed, ')');

        return ['p', { class: 'editor-citation', 'data-citation': '' }, ...parts];
    },
    addCommands() {
        return {
            insertCitation:
                (attrs: { source: string; url?: string; accessed?: string }) =>
                ({ commands }: any) =>
                    commands.insertContent({ type: this.name, attrs }),
        } as any;
    },
});
