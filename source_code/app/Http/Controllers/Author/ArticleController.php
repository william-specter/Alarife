<?php

namespace App\Http\Controllers\Author;

use App\Domain\Activity\RecordActivity;
use App\Domain\Articles\ChangeArticleStatus;
use App\Domain\Articles\RestoreRevision;
use App\Domain\Articles\SaveArticle;
use App\Domain\Content\ContentHtmlRenderer;
use App\Domain\Content\ContentMediaHydrator;
use App\Domain\Content\Slug;
use App\Domain\Realtime\Realtime;
use App\Domain\Themes\ThemeRegistry;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Encyclopedia;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ContentHtmlRenderer $renderer,
        private readonly SaveArticle $saver,
    ) {}

    public function index(Request $request, Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'topic' => (string) $request->query('topic', ''),
        ];

        $articles = $encyclopedia->articles()
            ->with(['topic', 'featuredImage', 'editor'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.$filters['q'].'%')
                ->orWhere('excerpt', 'like', '%'.$filters['q'].'%')))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['topic'] !== '', fn ($q) => $q->where('topic_id', (int) $filters['topic']))
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();
        $articles->through(fn (Article $article) => [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $article->status,
            'topic_id' => $article->topic_id,
            'topic' => $article->topic?->title,
            'words' => $this->renderer->wordCount($article->content ?? []),
            'updated_at' => $article->updated_at?->toIso8601String(),
            'editor' => $article->editor?->name,
            'published_at' => $article->published_at?->toIso8601String(),
        ]);

        return Inertia::render('articles/Index', [
            'encyclopedia' => [
                'id' => $encyclopedia->id,
                'title' => $encyclopedia->title,
                'slug' => $encyclopedia->slug,
                'status' => $encyclopedia->status,
                'theme_name' => app(ThemeRegistry::class)->find($encyclopedia->theme_key)?->name,
            ],
            'articles' => $articles,
            'topics' => $encyclopedia->topics()->orderBy('sort_order')->get(['id', 'title', 'parent_id']),
            'filters' => $filters,
            'counts' => [
                'all' => $encyclopedia->articles()->count(),
                'draft' => $encyclopedia->articles()->where('status', 'draft')->count(),
                'in_review' => $encyclopedia->articles()->where('status', 'in_review')->count(),
                'published' => $encyclopedia->articles()->where('status', 'published')->count(),
                'archived' => $encyclopedia->articles()->where('status', 'archived')->count(),
            ],
            'canWrite' => auth()->user()->can('write', $encyclopedia),
        ]);
    }

    public function store(Request $request, Encyclopedia $encyclopedia): RedirectResponse
    {
        $this->authorize('write', $encyclopedia);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'topic_id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string', 'max:180'],
        ]);

        if (! empty($data['topic_id']) && ! $encyclopedia->topics()->whereKey($data['topic_id'])->exists()) {
            throw ValidationException::withMessages(['topic_id' => 'Choose a topic from this encyclopedia.']);
        }

        $article = $encyclopedia->articles()->create([
            'topic_id' => $data['topic_id'] ?? null,
            'title' => $data['title'],
            'slug' => Slug::uniqueFor(Article::class, $encyclopedia->id, $data['slug'] ?? $data['title']),
            'content' => ['type' => 'doc', 'content' => []],
            'status' => 'draft',
            'sort_order' => (int) $encyclopedia->articles()->max('sort_order') + 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->saver->writeRevision($article, $request->user());
        app(RecordActivity::class)($encyclopedia, $request->user(), 'article.created', $article, ['title' => $article->title]);

        return redirect()->route('author.articles.edit', [$encyclopedia, $article]);
    }

    public function edit(Encyclopedia $encyclopedia, Article $article): Response
    {
        $this->authorize('update', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);
        $article->load(['revisions.editor', 'topic']);
        $media = $encyclopedia->media()->latest()->get();
        $content = app(ContentMediaHydrator::class)->hydrate($article->content, $media);

        return Inertia::render('articles/Edit', [
            'encyclopedia' => [
                'id' => $encyclopedia->id,
                'title' => $encyclopedia->title,
                'slug' => $encyclopedia->slug,
                'status' => $encyclopedia->status,
                'theme_name' => app(ThemeRegistry::class)->find($encyclopedia->theme_key)?->name,
            ],
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'content' => $content,
                'status' => $article->status,
                'topic_id' => $article->topic_id,
                'featured_image_id' => $article->featured_image_id,
                'revision_number' => $article->revision_number,
                'content_schema_version' => $article->content_schema_version,
                'updated_at' => $article->updated_at?->toIso8601String(),
                'published_at' => $article->published_at?->toIso8601String(),
                'words' => $this->renderer->wordCount($article->content ?? []),
                'reading_minutes' => max(1, (int) ceil($this->renderer->wordCount($article->content ?? []) / 220)),
            ],
            'topics' => $encyclopedia->topics()->orderBy('sort_order')->get(['id', 'title', 'parent_id']),
            'media' => $media->map(fn (MediaAsset $media) => [
                'id' => $media->id,
                'url' => $media->url(),
                'filename' => $media->filename,
                'alt_text' => $media->alt_text,
                'caption' => $media->caption,
                'width' => $media->width,
                'height' => $media->height,
            ]),
            'revisions' => $article->revisions->map(fn (ArticleRevision $revision) => [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'title' => $revision->title,
                'word_count' => $revision->word_count,
                'editor' => $revision->editor?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
            ]),
            'urls' => [
                'update' => route('author.articles.update', [$encyclopedia, $article]),
                'autosave' => route('author.articles.autosave', [$encyclopedia, $article]),
                'status' => route('author.articles.status', [$encyclopedia, $article]),
                'preview' => route('author.preview', ['encyclopedia' => $encyclopedia, 'page' => 'article', 'slug' => $article->slug]),
                'index' => route('author.articles.index', $encyclopedia),
            ],
            'canPublish' => auth()->user()->can('publish', $article),
            'canDelete' => auth()->user()->can('delete', $article),
        ]);
    }

    public function update(Request $request, Encyclopedia $encyclopedia, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);

        $data = $this->validatePayload($request, $article);
        ($this->saver)($article, $request->user(), $data, explicit: true);

        return back()->with('success', 'Article saved.');
    }

    public function autosave(Request $request, Encyclopedia $encyclopedia, Article $article): JsonResponse
    {
        $this->authorize('update', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);

        $data = $this->validatePayload($request, $article);
        $data['explicit'] = $request->boolean('explicit');
        $savedAt = now();

        try {
            ($this->saver)($article, $request->user(), $data, explicit: $data['explicit']);
        } catch (ConflictHttpException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'revision_number' => $article->fresh()->revision_number,
            ], 409);
        }

        return response()->json([
            'revision_number' => $article->fresh()->revision_number,
            'saved_at' => $savedAt->toIso8601String(),
            'words' => $this->renderer->wordCount($article->fresh()->content ?? []),
        ]);
    }

    public function revisions(Encyclopedia $encyclopedia, Article $article): JsonResponse
    {
        $this->authorize('update', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);

        return response()->json([
            'revisions' => $article->revisions()->with('editor')->get()->map(fn (ArticleRevision $revision) => [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'title' => $revision->title,
                'word_count' => $revision->word_count,
                'editor' => $revision->editor?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function status(Request $request, Encyclopedia $encyclopedia, Article $article, ChangeArticleStatus $change): RedirectResponse|JsonResponse
    {
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'in_review', 'published', 'archived'])],
        ]);

        if (in_array($data['status'], ['published', 'archived'], true)) {
            $this->authorize('publish', $article);
        } else {
            $this->authorize('update', $article);
        }

        $change($article, $data['status'], $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $article->status,
                'published_at' => $article->published_at?->toIso8601String(),
            ]);
        }

        return back()->with('success', 'Article status updated.');
    }

    public function destroy(Request $request, Encyclopedia $encyclopedia, Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);

        $title = $article->title;
        $article->delete();
        app(RecordActivity::class)($encyclopedia, $request->user(), 'article.deleted', null, ['title' => $title]);
        app(Realtime::class)->encyclopedia($encyclopedia, 'article.deleted', [
            'article_id' => $article->id,
            'title' => $title,
            'editor_id' => $request->user()->id,
        ]);

        return redirect()->route('author.articles.index', $encyclopedia)->with('success', 'Article deleted.');
    }

    public function duplicate(Request $request, Encyclopedia $encyclopedia, Article $article): RedirectResponse
    {
        $this->authorize('duplicate', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);

        $copy = $encyclopedia->articles()->create([
            'topic_id' => $article->topic_id,
            'title' => $article->title.' (copy)',
            'slug' => Slug::uniqueFor(Article::class, $encyclopedia->id, $article->slug.'-copy'),
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'featured_image_id' => $article->featured_image_id,
            'status' => 'draft',
            'sort_order' => (int) $encyclopedia->articles()->max('sort_order') + 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->saver->writeRevision($copy, $request->user());

        return redirect()->route('author.articles.edit', [$encyclopedia, $copy])->with('success', 'Article duplicated as a draft.');
    }

    public function restore(Request $request, Encyclopedia $encyclopedia, Article $article, ArticleRevision $revision, RestoreRevision $restore): RedirectResponse
    {
        $this->authorize('update', $article);
        abort_unless($article->encyclopedia_id === $encyclopedia->id, 404);
        abort_unless($revision->article_id === $article->id, 404);

        $restore($article, $revision, $request->user());

        return back()->with('success', 'Revision restored as a new version.');
    }

    private function validatePayload(Request $request, Article $article): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'slug' => ['nullable', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'array'],
            'topic_id' => ['nullable', 'integer'],
            'featured_image_id' => ['nullable', 'integer'],
            'revision_number' => ['required', 'integer', 'min:1'],
            'explicit' => ['sometimes', 'boolean'],
        ]);
    }
}
