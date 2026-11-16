<?php

namespace App\Http\Controllers\Author;

use App\Domain\Activity\RecordActivity;
use App\Domain\Content\ContentHtmlRenderer;
use App\Domain\Content\MediaUrlResolver;
use App\Domain\Encyclopedias\CoverManager;
use App\Domain\Media\StoreMedia;
use App\Domain\Publishing\PublishEncyclopedia;
use App\Domain\Rendering\SiteBuilder;
use App\Domain\Rendering\SiteContent;
use App\Domain\Themes\ThemeRegistry;
use App\Http\Controllers\Author\Concerns\PresentsEncyclopedia;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use App\Models\EncyclopediaCover;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    use PresentsEncyclopedia;

    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly ContentHtmlRenderer $renderer,
        private readonly SiteBuilder $builder,
    ) {}

    public function overview(Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);
        $encyclopedia->load(['covers.media', 'publications']);

        $articles = $encyclopedia->articles()->with('topic')->latest('updated_at')->limit(6)->get();
        $recent = $articles->map(fn ($article) => [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $article->status,
            'topic' => $article->topic?->title,
            'updated_at' => $article->updated_at?->toIso8601String(),
            'words' => $this->renderer->wordCount($article->content ?? []),
        ]);

        $counts = [
            'topics' => $encyclopedia->topics()->count(),
            'articles' => $encyclopedia->articles()->count(),
            'published' => $encyclopedia->articles()->where('status', 'published')->count(),
            'drafts' => $encyclopedia->articles()->whereIn('status', ['draft', 'in_review'])->count(),
            'words' => $encyclopedia->articles()->get()->sum(fn ($a) => $this->renderer->wordCount($a->content ?? [])),
        ];

        $latest = $encyclopedia->publications->sortByDesc('version')->first();

        return Inertia::render('encyclopedias/Overview', [
            'encyclopedia' => $this->encyclopediaCard($encyclopedia),
            'role' => $encyclopedia->membershipRole(auth()->user()),
            'counts' => $counts,
            'recentArticles' => $recent,
            'latestRelease' => $latest ? [
                'version' => $latest->version,
                'published_at' => $latest->published_at?->toIso8601String(),
                'article_count' => $latest->article_count,
                'topic_count' => $latest->topic_count,
            ] : null,
            'readiness' => $this->readiness($encyclopedia),
            'canPublish' => auth()->user()->can('publish', $encyclopedia),
            'canEdit' => auth()->user()->can('update', $encyclopedia),
        ]);
    }

    public function covers(Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);
        $encyclopedia->load('covers.media');

        return Inertia::render('encyclopedias/Covers', [
            'encyclopedia' => $this->encyclopediaCard($encyclopedia),
            'covers' => $encyclopedia->covers->sortBy('position')->values()->map(fn ($cover) => [
                'id' => $cover->id,
                'position' => $cover->position,
                'caption' => $cover->caption,
                'alt_text' => $cover->alt_text,
                'media' => [
                    'id' => $cover->media->id,
                    'url' => $cover->media->url(),
                    'filename' => $cover->media->filename,
                    'width' => $cover->media->width,
                    'height' => $cover->media->height,
                ],
            ]),
            'library' => $encyclopedia->media()->latest()->get()->map(fn (MediaAsset $media) => [
                'id' => $media->id,
                'url' => $media->url(),
                'filename' => $media->filename,
                'alt_text' => $media->alt_text,
                'width' => $media->width,
                'height' => $media->height,
            ]),
            'canManage' => auth()->user()->can('manageCovers', $encyclopedia),
            'maxCovers' => 3,
        ]);
    }

    public function storeCover(
        Request $request,
        Encyclopedia $encyclopedia,
        StoreMedia $storeMedia,
        CoverManager $covers,
    ): RedirectResponse {
        $this->authorize('manageCovers', $encyclopedia);

        $data = $request->validate([
            'file' => ['nullable', 'image', 'max:10240', 'required_without:media_asset_id'],
            'media_asset_id' => ['nullable', 'integer', 'required_without:file'],
            'caption' => ['nullable', 'string', 'max:300'],
            'alt_text' => ['nullable', 'string', 'max:300'],
        ]);

        if ($request->hasFile('file')) {
            $media = $storeMedia($encyclopedia, $request->user(), $request->file('file'), [
                'alt_text' => $data['alt_text'] ?? null,
                'caption' => $data['caption'] ?? null,
            ]);
        } else {
            $media = $encyclopedia->media()->findOrFail($data['media_asset_id']);
        }

        $covers->add($encyclopedia, $media, $data);

        return back()->with('success', 'Cover added.');
    }

    public function updateCover(Request $request, Encyclopedia $encyclopedia, EncyclopediaCover $cover, CoverManager $covers): RedirectResponse
    {
        $this->authorize('manageCovers', $encyclopedia);
        abort_unless($cover->encyclopedia_id === $encyclopedia->id, 404);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:300'],
            'alt_text' => ['nullable', 'string', 'max:300'],
        ]);

        $covers->update($cover, $data);

        return back()->with('success', 'Cover details updated.');
    }

    public function reorderCovers(Request $request, Encyclopedia $encyclopedia, CoverManager $covers): RedirectResponse
    {
        $this->authorize('manageCovers', $encyclopedia);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $covers->reorder($encyclopedia, $data['order']);

        return back()->with('success', 'Cover order saved.');
    }

    public function destroyCover(Encyclopedia $encyclopedia, EncyclopediaCover $cover, CoverManager $covers): RedirectResponse
    {
        $this->authorize('manageCovers', $encyclopedia);
        abort_unless($cover->encyclopedia_id === $encyclopedia->id, 404);

        $covers->remove($cover);

        return back()->with('success', 'Cover removed.');
    }

    public function appearance(Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);

        return Inertia::render('appearance/Index', [
            'encyclopedia' => $this->encyclopediaCard($encyclopedia),
            'themes' => $this->themes->all()->map(fn ($theme) => [
                'key' => $theme->key,
                'name' => $theme->name,
                'description' => $theme->description,
                'version' => $theme->version,
                'defaults' => $theme->defaultSettings(),
                'preview' => '/theme-previews/'.$theme->previewImage(),
            ])->values(),
            'settings' => $encyclopedia->theme_settings ?? $this->themes->default()->defaultSettings(),
            'themeKey' => $encyclopedia->theme_key,
            'coverStyle' => $encyclopedia->cover_style,
            'canManage' => auth()->user()->can('update', $encyclopedia),
        ]);
    }

    public function updateAppearance(Request $request, Encyclopedia $encyclopedia): RedirectResponse
    {
        $this->authorize('update', $encyclopedia);

        $data = $request->validate([
            'theme_key' => ['required', 'string', Rule::in($this->themes->all()->pluck('key')->all())],
            'cover_style' => ['required', Rule::in(['hero', 'editorial', 'cards'])],
            'settings' => ['required', 'array'],
        ]);

        $theme = $this->themes->findOrFail($data['theme_key']);
        $settings = $theme->normalize($data['settings']);

        $encyclopedia->update([
            'theme_key' => $theme->key,
            'theme_version' => $theme->version,
            'theme_settings' => $settings,
            'cover_style' => $data['cover_style'],
        ]);

        app(RecordActivity::class)($encyclopedia, $request->user(), 'theme.updated', $encyclopedia, [
            'theme' => $theme->name,
        ]);

        return back()->with('success', 'Theme saved.');
    }

    public function preview(Request $request, Encyclopedia $encyclopedia): HttpResponse
    {
        $this->authorize('view', $encyclopedia);

        $html = $this->renderPreview(
            $encyclopedia,
            (string) $request->query('page', 'home'),
            $request->query('slug'),
            $request->query('theme'),
            null,
            $request->query('device'),
        );

        return response($html)->header('Content-Type', 'text/html');
    }

    public function previewFrame(Request $request, Encyclopedia $encyclopedia): JsonResponse
    {
        $this->authorize('view', $encyclopedia);

        $data = $request->validate([
            'page' => ['nullable', 'string', Rule::in(['home', 'article', 'topic'])],
            'slug' => ['nullable', 'string'],
            'theme_key' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'cover_style' => ['nullable', Rule::in(['hero', 'editorial', 'cards'])],
        ]);

        $html = $this->renderPreview(
            $encyclopedia,
            $data['page'] ?? 'home',
            $data['slug'] ?? null,
            $data['theme_key'] ?? null,
            $data['settings'] ?? null,
            null,
            $data['cover_style'] ?? null,
        );

        return response()->json(['html' => $html]);
    }

    public function publishing(Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);
        $encyclopedia->load(['publications.exports']);

        $latest = $encyclopedia->publications->sortByDesc('version')->first();
        $archive = $latest?->exports->firstWhere('status', 'completed');

        return Inertia::render('publishing/Index', [
            'encyclopedia' => $this->encyclopediaCard($encyclopedia),
            'readiness' => $this->readiness($encyclopedia),
            'releases' => $encyclopedia->publications->sortByDesc('version')->values()->map(fn ($publication) => [
                'id' => $publication->id,
                'version' => $publication->version,
                'published_at' => $publication->published_at?->toIso8601String(),
                'article_count' => $publication->article_count,
                'topic_count' => $publication->topic_count,
                'theme_key' => $publication->theme_key,
                'theme_version' => $publication->theme_version,
                'notes' => $publication->notes,
                'publisher' => $publication->publisher?->name,
                'archive' => $publication->exports->firstWhere('status', 'completed') ? [
                    'id' => $publication->exports->firstWhere('status', 'completed')->id,
                    'size' => $publication->exports->firstWhere('status', 'completed')->archive_size,
                    'checksum' => $publication->exports->firstWhere('status', 'completed')->checksum,
                ] : null,
            ]),
            'canPublish' => auth()->user()->can('publish', $encyclopedia),
            'archiveReady' => (bool) $archive,
        ]);
    }

    public function publish(Request $request, Encyclopedia $encyclopedia, PublishEncyclopedia $publish): RedirectResponse
    {
        $this->authorize('publish', $encyclopedia);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $publication = $publish($encyclopedia, $request->user(), $data['notes'] ?? null);

        return back()->with('success', "Release v{$publication->version} published. Building the downloadable archive.");
    }

    public function unpublish(Request $request, Encyclopedia $encyclopedia): RedirectResponse
    {
        $this->authorize('publish', $encyclopedia);

        $encyclopedia->update(['status' => 'draft', 'published_at' => null]);

        app(RecordActivity::class)($encyclopedia, $request->user(), 'encyclopedia.unpublished', $encyclopedia);

        return back()->with('success', 'Encyclopedia unpublished. Existing downloads remain valid.');
    }

    private function renderPreview(
        Encyclopedia $encyclopedia,
        string $page,
        ?string $slug,
        ?string $themeKey,
        ?array $settings,
        ?string $device,
        ?string $coverStyle = null,
    ): string {
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: true, publishedOnly: false);
        $themeKey = $themeKey && $this->themes->has($themeKey) ? $themeKey : $encyclopedia->theme_key;
        $theme = $this->themes->findOrFail($themeKey);
        $resolved = $theme->normalize($settings ?? ($encyclopedia->theme_settings ?? []));

        $content = new SiteContent(
            encyclopedia: array_merge($content->encyclopedia, [
                'theme_key' => $theme->key,
                'theme_version' => $theme->version,
                'theme_settings' => $resolved,
                'cover_style' => $coverStyle ?? $content->encyclopedia['cover_style'],
                'visibility' => 'private',
            ]),
            covers: $content->covers,
            topics: $content->topics,
            articles: $content->articles,
            media: $content->media,
            release: $content->release,
            crossReferences: $content->crossReferences,
        );

        $mediaResolver = MediaUrlResolver::dynamic();
        $link = fn (string $path) => url($path);
        $site = $this->builder->site($content, $mediaResolver, $link, static: false, assetBase: '');
        $site['preview'] = true;
        $site['download_url'] = null;

        $html = match ($page) {
            'article' => (function () use ($content, $site, $slug, $mediaResolver, $link) {
                $article = $slug ? $content->articleBySlug($slug) : ($content->articles[0] ?? null);
                $pageData = $article
                    ? $this->builder->articlePage($content, $site, $article['slug'], $mediaResolver, $link)
                    : null;

                return $pageData
                    ? view('themes.shared.article', ['site' => $site, 'page' => $pageData])->render()
                    : view('themes.shared.not-found', ['site' => $site])->render();
            })(),
            'topic' => (function () use ($content, $site, $slug, $mediaResolver, $link) {
                $topic = $slug ? $content->topicBySlug($slug) : ($content->topics[0] ?? null);
                $pageData = $topic
                    ? $this->builder->topicPage($content, $site, $topic['slug'], $mediaResolver, $link)
                    : null;

                return $pageData
                    ? view('themes.shared.topic', ['site' => $site, 'page' => $pageData])->render()
                    : view('themes.shared.not-found', ['site' => $site])->render();
            })(),
            default => view('themes.'.$theme->key.'.home', [
                'site' => $site,
                'page' => $this->builder->homePage($content, $site),
            ])->render(),
        };

        return $html;
    }

    private function readiness(Encyclopedia $encyclopedia): array
    {
        $covers = $encyclopedia->covers()->count();
        $topics = $encyclopedia->topics()->count();
        $published = $encyclopedia->articles()->where('status', 'published')->count();
        $theme = $this->themes->has($encyclopedia->theme_key);

        return [
            ['key' => 'theme', 'label' => 'A theme is selected', 'done' => $theme, 'blocking' => true],
            ['key' => 'cover', 'label' => 'At least one cover image', 'done' => $covers > 0, 'blocking' => true],
            ['key' => 'topic', 'label' => 'At least one topic', 'done' => $topics > 0, 'blocking' => false],
            ['key' => 'article', 'label' => 'At least one published article', 'done' => $published > 0, 'blocking' => true],
        ];
    }
}
