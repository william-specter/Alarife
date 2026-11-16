<script setup lang="ts">
import { Callout, Citation, FigureImage, Gallery } from '@/editor/extensions';
import Highlight from '@tiptap/extension-highlight';
import Placeholder from '@tiptap/extension-placeholder';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import TaskItem from '@tiptap/extension-task-item';
import TaskList from '@tiptap/extension-task-list';
import TextAlign from '@tiptap/extension-text-align';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';

interface MediaItem {
    id: number;
    url: string;
    alt_text?: string | null;
    caption?: string | null;
    width?: number | null;
    height?: number | null;
}

const props = withDefaults(
    defineProps<{
        modelValue: Record<string, unknown> | null;
        placeholder?: string;
    }>(),
    { placeholder: 'Start writing. Use the toolbar to add headings, images, tables and references…' },
);

const emit = defineEmits<{ 'update:modelValue': [value: Record<string, unknown>]; ready: [editor: any] }>();

const emptyDoc = { type: 'doc', content: [{ type: 'paragraph' }] };
const initial =
    props.modelValue && Array.isArray((props.modelValue as any).content) && (props.modelValue as any).content.length ? props.modelValue : emptyDoc;

const editor = useEditor({
    content: initial as any,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                HTMLAttributes: { rel: 'noopener noreferrer' },
            },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
        Highlight.configure({ multicolor: false }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        FigureImage.configure({ allowBase64: false }),
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        TaskList,
        TaskItem.configure({ nested: true }),
        Callout,
        Gallery,
        Citation,
    ],
    editorProps: {
        attributes: {
            class: 'tiptap focus:outline-none',
            'aria-label': 'Article body',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getJSON() as Record<string, unknown>);
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value || !value) return;
        const current = JSON.stringify(editor.value.getJSON());
        if (JSON.stringify(value) === current) return;
        editor.value.commands.setContent(value as any, { emitUpdate: false } as any);
    },
);

watch(
    editor,
    (instance) => {
        if (instance) emit('ready', instance);
    },
    { immediate: true },
);

onBeforeUnmount(() => editor.value?.destroy());

function insertImage(media: MediaItem, alt?: string) {
    if (!editor.value) return;
    editor.value
        .chain()
        .focus()
        .insertContent({
            type: 'image',
            attrs: {
                mediaId: media.id,
                src: media.url,
                alt: alt ?? media.alt_text ?? '',
                caption: media.caption ?? '',
            },
        })
        .run();
}

function insertGallery(items: MediaItem[], layout: 'grid' | 'strip' = 'grid') {
    if (!editor.value || !items.length) return;
    (editor.value.chain().focus() as any)
        .insertGallery({
            layout,
            images: items.map((item) => ({
                mediaId: item.id,
                src: item.url,
                alt: item.alt_text ?? '',
                caption: item.caption ?? '',
            })),
        })
        .run();
}

function wordCount(): number {
    if (!editor.value) return 0;
    return (editor.value.getText().match(/[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*/gu) ?? []).length;
}

function getJson(): Record<string, unknown> | null {
    return (editor.value?.getJSON() as Record<string, unknown>) ?? null;
}

function focus() {
    editor.value?.commands.focus();
}

defineExpose({ editor, insertImage, insertGallery, wordCount, getJson, focus });
</script>

<template>
    <EditorContent :editor="editor" />
</template>
