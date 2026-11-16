<?php

namespace App\Http\Controllers\Reader;

use App\Domain\Content\MediaUrlResolver;
use App\Domain\Exports\RequestExport;
use App\Domain\Rendering\SiteBuilder;
use App\Domain\Rendering\SiteContent;
use App\Domain\Search\EncyclopediaSearch;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRedirect;
use App\Models\Encyclopedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReaderController extends Controller
{
    public function __construct(
        private readonly SiteBuilder $builder,
        private readonly EncyclopediaSearch $search,
    ) {}

    public function home(Encyclopedia $encyclopedia): Response
    {
        $this->ensureVisible($encyclopedia);
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: false, publishedOnly: true);
        $media = MediaUrlResolver::dynamic();
        $site = $this->builder->site(
            $content,
            $media,
            fn (string $path) => url($path),
            static: false,
            assetBase: '',
            downloadUrl: $this->downloadUrl($encyclopedia),
        );

        return $this->respond($encyclopedia, 'themes.'.$content->encyclopedia['theme_key'].'.home', [
            'site' => $site,
            'page' => $this->builder->homePage($content, $site),
        ]);
    }

    public function topic(Encyclopedia $encyclopedia, string $topicSlug): Response
    {
        $this->ensureVisible($encyclopedia);
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: false, publishedOnly: true);
        $topic = $content->topicBySlug($topicSlug);
        abort_if($topic === null || ($topic['status'] ?? 'draft') !== 'published', 404);

        $media = MediaUrlResolver::dynamic();
        $site = $this->builder->site(
            $content,
            $media,
            fn (string $path) => url($path),
            static: false,
            assetBase: '',
            downloadUrl: $this->downloadUrl($encyclopedia),
        );
        $page = $this->builder->topicPage($content, $site, $topicSlug, $media, $site['link']);
        abort_if($page === null, 404);

        return $this->respond($encyclopedia, 'themes.shared.topic', ['site' => $site, 'page' => $page]);
    }

    public function article(Encyclopedia $encyclopedia, string $articleSlug): Response|RedirectResponse
    {
        $this->ensureVisible($encyclopedia);
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: false, publishedOnly: true);
        $article = $content->articleBySlug($articleSlug);

        if ($article === null) {
            $redirect = ArticleRedirect::query()
                ->where('encyclopedia_id', $encyclopedia->id)
                ->where('from_slug', $articleSlug)
                ->with('article')
                ->first();
            if ($redirect && $redirect->article?->isPublished()) {
                return redirect()->route('reader.article', [$encyclopedia, $redirect->article->slug], 301);
            }

            abort(404);
        }

        $media = MediaUrlResolver::dynamic();
        $site = $this->builder->site(
            $content,
            $media,
            fn (string $path) => url($path),
            static: false,
            assetBase: '',
            downloadUrl: $this->downloadUrl($encyclopedia),
        );
        $page = $this->builder->articlePage($content, $site, $articleSlug, $media, $site['link']);
        abort_if($page === null, 404);

        return $this->respond($encyclopedia, 'themes.shared.article', ['site' => $site, 'page' => $page]);
    }

    public function search(Request $request, Encyclopedia $encyclopedia): Response
    {
        $this->ensureVisible($encyclopedia);
        $query = trim((string) $request->query('q', ''));
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: false, publishedOnly: true);
        $media = MediaUrlResolver::dynamic();
        $site = $this->builder->site(
            $content,
            $media,
            fn (string $path) => url($path),
            static: false,
            assetBase: '',
            downloadUrl: $this->downloadUrl($encyclopedia),
        );

        $found = $this->search->search($encyclopedia->id, $query);
        $results = $found['articles']->map(fn (Article $article) => $this->builder->card([
            'id' => $article->id,
            'topic_id' => $article->topic_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'featured_media_id' => $article->featured_image_id,
            'sort_order' => $article->sort_order,
            'published_at' => $article->published_at?->toIso8601String(),
        ], $content, $media, $site['link']))->values();

        $topics = $found['topics']->map(fn ($topic) => [
            'title' => $topic->title,
            'description' => $topic->description,
            'url' => $site['link']('/'.$encyclopedia->slug.'/topics/'.$topic->slug),
        ])->values();

        $page = $this->builder->searchPage($content, $site, $query, [
            'articles' => $results,
            'topics' => $topics,
            'count' => $results->count() + $topics->count(),
        ]);

        return $this->respond($encyclopedia, 'themes.shared.search', ['site' => $site, 'page' => $page]);
    }

    public function download(Encyclopedia $encyclopedia, RequestExport $exports)
    {
        $this->ensureVisible($encyclopedia);
        abort_unless($encyclopedia->export_enabled === true, 404);

        $publication = $encyclopedia->latestPublication();
        abort_if($publication === null, 404);

        $archive = $exports->publicArchive($encyclopedia);
        if (! $archive || ! $archive->archiveExists()) {
            $exports($encyclopedia, null, [], $publication, true);

            return back()->with('info', 'The downloadable archive is being prepared. Please try again in a moment.');
        }

        return Storage::disk('local')->download($archive->archive_path, $encyclopedia->slug.'.zip');
    }

    private function ensureVisible(Encyclopedia $encyclopedia): void
    {
        abort_unless($encyclopedia->isLinkAccessible(), 404);
    }

    private function downloadUrl(Encyclopedia $encyclopedia): ?string
    {
        if (! $encyclopedia->export_enabled) {
            return null;
        }

        return route('reader.download', $encyclopedia);
    }

    private function respond(Encyclopedia $encyclopedia, string $view, array $data): Response
    {
        $html = view($view, $data)->render();
        $response = response($html)->header('Content-Type', 'text/html; charset=UTF-8');
        $response->header('Cache-Control', $encyclopedia->visibility === 'public' ? 'public, max-age=60' : 'no-store');
        $response->header('X-Robots-Tag', $encyclopedia->visibility === 'public' ? 'all' : 'noindex, nofollow');

        return $response;
    }
}
