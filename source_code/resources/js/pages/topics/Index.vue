<script setup lang="ts">
import AuthorLayout from '@/components/author/AuthorLayout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronRight, FileText, FolderPlus, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Topic {
    id: number;
    parent_id: number | null;
    title: string;
    slug: string;
    description: string | null;
    status: string;
    sort_order: number;
    articles_count: number;
    children_count: number;
}

const props = defineProps<{
    encyclopedia: { id: number; title: string; slug: string };
    topics: Topic[];
    canManage: boolean;
}>();

const selectedId = ref<number | null>(props.topics[0]?.id ?? null);
const mode = ref<'edit' | 'create'>('edit');
const expanded = ref<number[]>(props.topics.filter((topic) => topic.parent_id === null).map((topic) => topic.id));
const confirmDelete = ref(false);

const selected = computed(() => props.topics.find((topic) => topic.id === selectedId.value) ?? null);
const roots = computed(() => props.topics.filter((topic) => topic.parent_id === null).sort((a, b) => a.sort_order - b.sort_order));
const childrenOf = (id: number) => props.topics.filter((topic) => topic.parent_id === id).sort((a, b) => a.sort_order - b.sort_order);

const form = useForm({
    title: '',
    slug: '',
    description: '',
    parent_id: null as number | null,
    status: 'draft' as 'draft' | 'published',
    sort_order: 0,
});

function loadTopic(topic: Topic) {
    mode.value = 'edit';
    selectedId.value = topic.id;
    form.clearErrors();
    form.title = topic.title;
    form.slug = topic.slug;
    form.description = topic.description ?? '';
    form.parent_id = topic.parent_id;
    form.status = topic.status as 'draft' | 'published';
    form.sort_order = topic.sort_order;
}

function createTopic(parentId: number | null = null) {
    mode.value = 'create';
    selectedId.value = null;
    form.clearErrors();
    form.title = '';
    form.slug = '';
    form.description = '';
    form.parent_id = parentId;
    form.status = 'draft';
    form.sort_order = props.topics.length + 1;
    if (parentId && !expanded.value.includes(parentId)) expanded.value.push(parentId);
}

watch(
    () => props.topics,
    (topics) => {
        if (selectedId.value && !topics.some((topic) => topic.id === selectedId.value)) {
            if (topics.length) loadTopic(topics[0]);
            else createTopic();
        }
    },
    { deep: true },
);

if (props.topics.length) loadTopic(props.topics[0]);
else createTopic();

function submit() {
    if (mode.value === 'create') {
        form.post(route('author.topics.store', props.encyclopedia.slug), { preserveScroll: true, onSuccess: () => (mode.value = 'edit') });
    } else if (selected.value) {
        form.put(route('author.topics.update', [props.encyclopedia.slug, selected.value.id]), { preserveScroll: true });
    }
}

function remove() {
    if (!selected.value) return;
    const removingId = selected.value.id;
    router.delete(route('author.topics.destroy', [props.encyclopedia.slug, removingId]), {
        preserveScroll: true,
        onSuccess: () => {
            confirmDelete.value = false;
            const next = props.topics.find((topic) => topic.id !== removingId);
            if (next) loadTopic(next);
            else createTopic();
        },
    });
}

function toggle(id: number) {
    expanded.value = expanded.value.includes(id) ? expanded.value.filter((item) => item !== id) : [...expanded.value, id];
}

function move(topic: Topic, direction: -1 | 1) {
    const siblings = (topic.parent_id === null ? roots.value : childrenOf(topic.parent_id)).slice();
    const index = siblings.findIndex((item) => item.id === topic.id);
    const target = siblings[index + direction];
    if (!target) return;
    const payload = props.topics.map((item) => ({
        id: item.id,
        parent_id: item.parent_id,
        sort_order: item.id === topic.id ? target.sort_order : item.id === target.id ? topic.sort_order : item.sort_order,
    }));
    router.put(route('author.topics.reorder', props.encyclopedia.slug), { topics: payload }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Topics · ${encyclopedia.title}`" />

    <AuthorLayout :encyclopedia="encyclopedia" active="topics" :breadcrumbs="[{ label: 'Topics' }]">
        <PageHeader
            eyebrow="Structure"
            title="Topics"
            description="Two levels only: root subjects and their subtopics. Articles belong to one primary topic each."
        >
            <template #actions>
                <button v-if="canManage" class="btn-primary" type="button" @click="createTopic()"><Plus class="size-4" /> New topic</button>
            </template>
        </PageHeader>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
            <!-- Tree -->
            <div class="surface overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">{{ props.topics.length }} topics</p>
                    <button v-if="canManage" class="btn-ghost btn-sm size-7 p-0" type="button" aria-label="Add root topic" @click="createTopic()">
                        <FolderPlus class="size-4" />
                    </button>
                </div>
                <div v-if="roots.length" class="max-h-[62vh] overflow-y-auto p-2">
                    <div v-for="root in roots" :key="root.id">
                        <div
                            class="group flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-2 transition-colors"
                            :class="selectedId === root.id && mode === 'edit' ? 'bg-secondary' : 'hover:bg-secondary/60'"
                            @click="loadTopic(root)"
                        >
                            <button
                                class="rounded p-0.5 text-muted-foreground hover:text-foreground"
                                type="button"
                                :aria-label="`Toggle ${root.title}`"
                                @click.stop="toggle(root.id)"
                            >
                                <ChevronRight class="size-3.5 transition-transform" :class="expanded.includes(root.id) ? 'rotate-90' : ''" />
                            </button>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium">{{ root.title }}</span>
                                <span class="block truncate text-[10px] text-muted-foreground"
                                    >/{{ root.slug }} · {{ root.articles_count }} articles</span
                                >
                            </span>
                            <StatusBadge v-if="root.status === 'draft'" value="draft" />
                            <span class="hidden items-center gap-0.5 group-hover:flex">
                                <button class="btn-ghost size-6 p-0" type="button" aria-label="Move up" @click.stop="move(root, -1)">↑</button>
                                <button class="btn-ghost size-6 p-0" type="button" aria-label="Move down" @click.stop="move(root, 1)">↓</button>
                                <button
                                    v-if="canManage"
                                    class="btn-ghost size-6 p-0"
                                    type="button"
                                    aria-label="Add subtopic"
                                    @click.stop="createTopic(root.id)"
                                >
                                    <Plus class="size-3" />
                                </button>
                            </span>
                        </div>
                        <div v-if="expanded.includes(root.id)" class="ml-5 border-l border-border pl-2">
                            <div
                                v-for="child in childrenOf(root.id)"
                                :key="child.id"
                                class="group flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-[12px] transition-colors"
                                :class="selectedId === child.id && mode === 'edit' ? 'bg-secondary' : 'hover:bg-secondary/60'"
                                @click="loadTopic(child)"
                            >
                                <FileText class="size-3 text-muted-foreground" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ child.title }}</span>
                                    <span class="block truncate text-[10px] text-muted-foreground">{{ child.articles_count }} articles</span>
                                </span>
                                <button class="btn-ghost size-5 p-0" type="button" aria-label="Move up" @click.stop="move(child, -1)">↑</button>
                                <button class="btn-ghost size-5 p-0" type="button" aria-label="Move down" @click.stop="move(child, 1)">↓</button>
                            </div>
                            <p v-if="!childrenOf(root.id).length" class="px-2 py-1.5 text-[11px] text-muted-foreground">No subtopics</p>
                        </div>
                    </div>
                </div>
                <div v-else class="px-4 py-10 text-center">
                    <p class="text-sm text-muted-foreground">No topics yet.</p>
                    <button v-if="canManage" class="btn-primary btn-sm mt-3" type="button" @click="createTopic()">
                        <Plus class="size-3.5" /> Create the first topic
                    </button>
                </div>
            </div>

            <!-- Editor -->
            <div class="surface p-5">
                <div class="mb-5 flex items-start justify-between gap-3 border-b border-border pb-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">
                            {{ mode === 'create' ? 'New topic' : 'Edit topic' }}
                        </p>
                        <h2 class="font-display text-2xl">{{ mode === 'create' ? 'Create a topic' : selected?.title }}</h2>
                    </div>
                    <button
                        v-if="mode === 'edit' && selected && canManage"
                        class="btn-ghost btn-sm text-destructive"
                        type="button"
                        @click="confirmDelete = true"
                    >
                        <Trash2 class="size-3.5" /> Delete
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                        <div>
                            <label class="label" for="topic-title">Title</label>
                            <input id="topic-title" v-model="form.title" class="input" placeholder="Ancient Egypt" :disabled="!canManage" />
                            <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="label" for="topic-slug">Slug</label>
                            <input
                                id="topic-slug"
                                v-model="form.slug"
                                class="input font-mono text-xs"
                                placeholder="ancient-egypt"
                                :disabled="!canManage"
                            />
                            <p v-if="form.errors.slug" class="field-error">{{ form.errors.slug }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="topic-description">Description</label>
                        <textarea
                            id="topic-description"
                            v-model="form.description"
                            rows="4"
                            class="textarea"
                            placeholder="A short introduction shown on the topic page."
                            :disabled="!canManage"
                        />
                        <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label" for="topic-parent">Parent</label>
                            <select id="topic-parent" v-model="form.parent_id" class="input" :disabled="!canManage">
                                <option :value="null">Root topic</option>
                                <option v-for="root in roots.filter((item) => item.id !== selected?.id)" :key="root.id" :value="root.id">
                                    {{ root.title }}
                                </option>
                            </select>
                            <p v-if="form.errors.parent_id" class="field-error">{{ form.errors.parent_id }}</p>
                        </div>
                        <div>
                            <label class="label" for="topic-status">Status</label>
                            <select id="topic-status" v-model="form.status" class="input" :disabled="!canManage">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="topic-order">Sort order</label>
                            <input id="topic-order" v-model.number="form.sort_order" type="number" min="0" class="input" :disabled="!canManage" />
                        </div>
                    </div>

                    <div v-if="mode === 'edit' && selected" class="rounded-lg border border-border bg-secondary/40 p-3 text-xs text-muted-foreground">
                        <div class="flex flex-wrap gap-x-6 gap-y-1">
                            <span
                                ><strong class="text-foreground">{{ selected.articles_count }}</strong> articles</span
                            >
                            <span
                                ><strong class="text-foreground">{{ selected.children_count }}</strong> subtopics</span
                            >
                            <span v-if="selected.status === 'published'">Visible on the public topic page</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-border pt-4">
                        <button class="btn-ghost btn-sm" type="button" @click="createTopic()">+ Another topic</button>
                        <button v-if="canManage" class="btn-primary" type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : mode === 'create' ? 'Create topic' : 'Save changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmDelete"
            title="Delete this topic?"
            :description="`“${selected?.title}” will be removed. Its articles stay in the encyclopedia but become unassigned.`"
            confirm-label="Delete topic"
            tone="danger"
            @confirm="remove"
        />
    </AuthorLayout>
</template>
