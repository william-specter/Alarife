<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignLeft,
    Bold,
    Braces,
    Code,
    Heading2,
    Heading3,
    Heading4,
    Image as ImageIcon,
    Images,
    Italic,
    Link2,
    List,
    ListChecks,
    ListOrdered,
    MessageSquareQuote,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Table as TableIcon,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ editor: Editor }>();
const emit = defineEmits<{ image: []; gallery: [] }>();

const tick = ref(0);
let handler: (() => void) | null = null;

onMounted(() => {
    handler = () => tick.value++;
    props.editor.on('transaction', handler);
});

onBeforeUnmount(() => {
    if (handler) props.editor.off('transaction', handler);
});

function isActive(name: string, attrs?: Record<string, unknown>) {
    void tick.value;
    return props.editor.isActive(name, attrs);
}

function canUndo() {
    void tick.value;
    return props.editor.can().undo();
}

function canRedo() {
    void tick.value;
    return props.editor.can().redo();
}

function setLink() {
    const previous = props.editor.getAttributes('link').href ?? '';
    const url = window.prompt('Link URL (https://…, /path or mailto:)', previous);
    if (url === null) return;
    if (url === '') {
        props.editor.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    props.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function addTable() {
    props.editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

function addCallout() {
    const title = window.prompt('Callout title (optional)', 'Note') ?? '';
    props.editor
        .chain()
        .focus()
        .insertContent({
            type: 'callout',
            attrs: { tone: 'note', title },
            content: [{ type: 'paragraph' }],
        })
        .run();
}

function addCitation() {
    const source = window.prompt('Citation source (author, title, year)', '');
    if (!source) return;
    const url = window.prompt('Source URL (optional)', '') ?? '';
    (props.editor.chain().focus() as any).insertCitation({ source, url }).run();
}

function insertRule() {
    props.editor.chain().focus().setHorizontalRule().run();
}

const buttons = [
    { label: 'Undo', icon: Undo2, action: () => props.editor.chain().focus().undo().run(), disabled: () => !canUndo() },
    { label: 'Redo', icon: Redo2, action: () => props.editor.chain().focus().redo().run(), disabled: () => !canRedo() },
];
</script>

<template>
    <div class="flex flex-wrap items-center gap-0.5 rounded-lg border border-border bg-card p-1 shadow-sm">
        <button
            v-for="button in buttons"
            :key="button.label"
            class="toolbar-btn"
            type="button"
            :title="button.label"
            :disabled="button.disabled()"
            @click="button.action()"
        >
            <component :is="button.icon" class="size-3.5" />
        </button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

        <button
            class="toolbar-btn"
            :class="isActive('heading', { level: 2 }) ? 'toolbar-btn-active' : ''"
            type="button"
            title="Heading 2"
            @click="props.editor.chain().focus().toggleHeading({ level: 2 }).run()"
        >
            <Heading2 class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('heading', { level: 3 }) ? 'toolbar-btn-active' : ''"
            type="button"
            title="Heading 3"
            @click="props.editor.chain().focus().toggleHeading({ level: 3 }).run()"
        >
            <Heading3 class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('heading', { level: 4 }) ? 'toolbar-btn-active' : ''"
            type="button"
            title="Heading 4"
            @click="props.editor.chain().focus().toggleHeading({ level: 4 }).run()"
        >
            <Heading4 class="size-3.5" />
        </button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

        <button
            class="toolbar-btn"
            :class="isActive('bold') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Bold"
            @click="props.editor.chain().focus().toggleBold().run()"
        >
            <Bold class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('italic') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Italic"
            @click="props.editor.chain().focus().toggleItalic().run()"
        >
            <Italic class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('underline') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Underline"
            @click="props.editor.chain().focus().toggleUnderline().run()"
        >
            <UnderlineIcon class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('strike') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Strikethrough"
            @click="props.editor.chain().focus().toggleStrike().run()"
        >
            <Strikethrough class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('code') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Inline code"
            @click="props.editor.chain().focus().toggleCode().run()"
        >
            <Code class="size-3.5" />
        </button>
        <button class="toolbar-btn" :class="isActive('link') ? 'toolbar-btn-active' : ''" type="button" title="Link" @click="setLink">
            <Link2 class="size-3.5" />
        </button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

        <button
            class="toolbar-btn"
            :class="isActive('bulletList') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Bullet list"
            @click="props.editor.chain().focus().toggleBulletList().run()"
        >
            <List class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('orderedList') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Ordered list"
            @click="props.editor.chain().focus().toggleOrderedList().run()"
        >
            <ListOrdered class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('taskList') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Task list"
            @click="props.editor.chain().focus().toggleTaskList().run()"
        >
            <ListChecks class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('blockquote') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Quote"
            @click="props.editor.chain().focus().toggleBlockquote().run()"
        >
            <Quote class="size-3.5" />
        </button>
        <button
            class="toolbar-btn"
            :class="isActive('codeBlock') ? 'toolbar-btn-active' : ''"
            type="button"
            title="Code block"
            @click="props.editor.chain().focus().toggleCodeBlock().run()"
        >
            <Braces class="size-3.5" />
        </button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

        <button class="toolbar-btn" type="button" title="Image" @click="emit('image')"><ImageIcon class="size-3.5" /></button>
        <button class="toolbar-btn" type="button" title="Gallery" @click="emit('gallery')"><Images class="size-3.5" /></button>
        <button class="toolbar-btn" type="button" title="Table" @click="addTable"><TableIcon class="size-3.5" /></button>
        <button class="toolbar-btn" type="button" title="Callout" @click="addCallout"><MessageSquareQuote class="size-3.5" /></button>
        <button class="toolbar-btn" type="button" title="Citation" @click="addCitation">Cite</button>
        <button class="toolbar-btn" type="button" title="Divider" @click="insertRule"><Minus class="size-3.5" /></button>
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
        <button class="toolbar-btn" type="button" title="Align left" @click="props.editor.chain().focus().setTextAlign('left').run()">
            <AlignLeft class="size-3.5" />
        </button>
        <button class="toolbar-btn" type="button" title="Align center" @click="props.editor.chain().focus().setTextAlign('center').run()">
            <AlignCenter class="size-3.5" />
        </button>
    </div>
</template>

<style scoped>
.toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.375rem;
    font-size: 0.65rem;
    font-weight: 600;
    color: hsl(var(--muted-foreground));
    transition:
        background-color 0.12s ease,
        color 0.12s ease;
}
.toolbar-btn:hover {
    background: hsl(var(--secondary));
    color: hsl(var(--foreground));
}
.toolbar-btn:disabled {
    opacity: 0.35;
}
.toolbar-btn-active {
    background: hsl(var(--foreground));
    color: hsl(var(--background));
}
</style>
