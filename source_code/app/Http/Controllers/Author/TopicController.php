<?php

namespace App\Http\Controllers\Author;

use App\Domain\Activity\RecordActivity;
use App\Domain\Content\Slug;
use App\Domain\Themes\ThemeRegistry;
use App\Domain\Topics\ReorderTopics;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function index(Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);

        $topics = $encyclopedia->topics()->withCount(['articles', 'children'])->orderBy('sort_order')->get();

        return Inertia::render('topics/Index', [
            'encyclopedia' => [
                'id' => $encyclopedia->id,
                'title' => $encyclopedia->title,
                'slug' => $encyclopedia->slug,
                'status' => $encyclopedia->status,
                'theme_name' => app(ThemeRegistry::class)->find($encyclopedia->theme_key)?->name,
            ],
            'topics' => $topics->map(fn (Topic $topic) => $this->present($topic)),
            'canManage' => auth()->user()->can('manageContent', $encyclopedia),
        ]);
    }

    public function store(Request $request, Encyclopedia $encyclopedia): RedirectResponse
    {
        $this->authorize('manageContent', $encyclopedia);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'slug' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $parentId = null;
        if (! empty($data['parent_id'])) {
            $parent = $encyclopedia->topics()->find($data['parent_id']);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a topic from this encyclopedia.']);
            }
            $parentId = $parent->parent_id === null ? $parent->id : $parent->parent_id;
        }

        $topic = $encyclopedia->topics()->create([
            'parent_id' => $parentId,
            'title' => $data['title'],
            'slug' => Slug::uniqueFor(Topic::class, $encyclopedia->id, $data['slug'] ?? $data['title']),
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'sort_order' => $data['sort_order'] ?? ((int) $encyclopedia->topics()->max('sort_order') + 1),
        ]);

        app(RecordActivity::class)($encyclopedia, $request->user(), 'topic.created', $topic, ['title' => $topic->title]);

        return back()->with('success', 'Topic created.');
    }

    public function update(Request $request, Encyclopedia $encyclopedia, Topic $topic): RedirectResponse
    {
        $this->authorize('manageContent', $encyclopedia);
        abort_unless($topic->encyclopedia_id === $encyclopedia->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'slug' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $parentId = null;
        if (! empty($data['parent_id'])) {
            $parent = $encyclopedia->topics()->find($data['parent_id']);
            if (! $parent || $parent->id === $topic->id || $parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a root topic from this encyclopedia.']);
            }
            $parentId = $parent->id;
        }

        if ($encyclopedia->topics()->where('parent_id', $topic->id)->exists() && $parentId !== null) {
            throw ValidationException::withMessages(['parent_id' => 'A topic with children cannot become a child.']);
        }

        $topic->update([
            'parent_id' => $parentId,
            'title' => $data['title'],
            'slug' => Slug::uniqueFor(Topic::class, $encyclopedia->id, $data['slug'], $topic->id),
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'sort_order' => $data['sort_order'] ?? $topic->sort_order,
        ]);

        app(RecordActivity::class)($encyclopedia, $request->user(), 'topic.updated', $topic, ['title' => $topic->title]);

        return back()->with('success', 'Topic updated.');
    }

    public function destroy(Request $request, Encyclopedia $encyclopedia, Topic $topic): RedirectResponse
    {
        $this->authorize('manageContent', $encyclopedia);
        abort_unless($topic->encyclopedia_id === $encyclopedia->id, 404);

        $title = $topic->title;
        $topic->delete();

        app(RecordActivity::class)($encyclopedia, $request->user(), 'topic.deleted', null, ['title' => $title]);

        return back()->with('success', 'Topic deleted. Its articles are now unassigned.');
    }

    public function reorder(Request $request, Encyclopedia $encyclopedia, ReorderTopics $reorder): RedirectResponse
    {
        $this->authorize('manageContent', $encyclopedia);

        $data = $request->validate([
            'topics' => ['required', 'array'],
            'topics.*.id' => ['required', 'integer'],
            'topics.*.parent_id' => ['nullable', 'integer'],
            'topics.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $reorder($encyclopedia, $data['topics']);

        return back()->with('success', 'Topic order saved.');
    }

    private function present(Topic $topic): array
    {
        return [
            'id' => $topic->id,
            'parent_id' => $topic->parent_id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'description' => $topic->description,
            'status' => $topic->status,
            'sort_order' => $topic->sort_order,
            'articles_count' => $topic->articles_count ?? 0,
            'children_count' => $topic->children_count ?? 0,
        ];
    }
}
