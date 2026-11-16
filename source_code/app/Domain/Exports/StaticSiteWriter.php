<?php

namespace App\Domain\Exports;

use App\Domain\Content\LinkRewriter;
use App\Domain\Content\MediaUrlResolver;
use App\Domain\Rendering\SiteBuilder;
use App\Domain\Rendering\SiteContent;
use App\Domain\Themes\ThemeRegistry;
use App\Domain\Themes\ThemeStylesheet;
use App\Models\EncyclopediaExport;
use Illuminate\Support\Facades\File;
use RuntimeException;

class StaticSiteWriter
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly ThemeStylesheet $stylesheets,
        private readonly SiteBuilder $builder,
        private readonly MediaCollector $mediaCollector,
        private readonly SearchIndexBuilder $searchIndex,
        private readonly LinkValidator $validator,
    ) {}

    /**
     * @return array{pages: int, media: int, warnings: array<int, string>, directory: string}
     */
    public function write(EncyclopediaExport $export, array $snapshot, string $siteDir): array
    {
        if (is_dir($siteDir)) {
            File::deleteDirectory($siteDir);
        }
        File::ensureDirectoryExists($siteDir);

        $theme = $this->themes->findOrFail($export->template_key);
        if ($export->publication && version_compare($theme->version, $export->template_version, '<')) {
            throw new RuntimeException(
                "Theme [{$theme->key}] is downgraded (running {$theme->version}, release pinned {$export->template_version}). Refusing to export."
            );
        }

        $content = SiteContent::fromSnapshot($snapshot);
        $content = new SiteContent(
            encyclopedia: array_merge($content->encyclopedia, [
                'theme_settings' => $snapshot['theme']['settings'] ?? [],
                'theme_key' => $snapshot['theme']['key'] ?? $export->template_key,
                'theme_version' => $snapshot['theme']['version'] ?? $export->template_version,
                'cover_style' => $snapshot['theme']['cover_style'] ?? $content->encyclopedia['cover_style'],
            ]),
            covers: $content->covers,
            topics: $content->topics,
            articles: $content->articles,
            media: $content->media,
            release: $content->release,
            crossReferences: $content->crossReferences,
        );

        $options = $export->options ?? [];
        $mediaResult = $this->mediaCollector->collect($content, $siteDir);
        $slug = $content->encyclopedia['slug'];
        $themeKey = $export->template_key;

        $resolverFor = function (string $base) use ($mediaResult): MediaUrlResolver {
            return MediaUrlResolver::forExport(array_map(fn (string $path) => $base.$path, $mediaResult['map']));
        };

        $makeSite = function (string $base, MediaUrlResolver $mediaResolver) use ($content, $export, $slug): array {
            $link = LinkRewriter::forExport($slug, $base);

            $site = $this->builder->site(
                $content,
                $mediaResolver,
                $link,
                static: true,
                assetBase: $base,
                downloadUrl: null,
            );
            $site['include_search'] = (bool) ($export->options['include_search'] ?? true);
            $site['include_javascript'] = (bool) ($export->options['include_javascript'] ?? true);
            if (! $site['include_search']) {
                $site['search_url'] = $site['home_url'];
            }

            return $site;
        };

        $pages = 0;

        // Homepage
        $homeMedia = $resolverFor('');
        $site = $makeSite('', $homeMedia);
        $page = $this->builder->homePage($content, $site);
        $this->put($siteDir.'/index.html', view("themes.{$themeKey}.home", ['site' => $site, 'page' => $page])->render());
        $pages++;

        // Topic pages
        foreach ($content->topics as $topic) {
            $base = '../../';
            $mediaResolver = $resolverFor($base);
            $site = $makeSite($base, $mediaResolver);
            $page = $this->builder->topicPage($content, $site, $topic['slug'], $mediaResolver, $site['link']);
            if ($page === null) {
                continue;
            }
            $this->put(
                $siteDir.'/topics/'.$topic['slug'].'/index.html',
                view('themes.shared.topic', ['site' => $site, 'page' => $page])->render()
            );
            $pages++;
        }

        // Article pages
        foreach ($content->articles as $article) {
            $base = '../../';
            $mediaResolver = $resolverFor($base);
            $site = $makeSite($base, $mediaResolver);
            $page = $this->builder->articlePage($content, $site, $article['slug'], $mediaResolver, $site['link']);
            if ($page === null) {
                continue;
            }
            $this->put(
                $siteDir.'/articles/'.$article['slug'].'/index.html',
                view('themes.shared.article', ['site' => $site, 'page' => $page])->render()
            );
            $pages++;
        }

        // Search page
        if ($options['include_search'] ?? true) {
            $base = '../';
            $site = $makeSite($base, $resolverFor($base));
            $page = $this->builder->searchPage($content, $site, '', []);
            $this->put(
                $siteDir.'/search/index.html',
                view('themes.shared.search', ['site' => $site, 'page' => $page])->render()
            );
            $this->put($siteDir.'/assets/search/search-index.js', $this->searchIndex->javascript($content));
            $pages++;
        }

        // 404
        $site = $makeSite('', $resolverFor(''));
        $this->put($siteDir.'/404.html', view('themes.shared.not-found', ['site' => $site])->render());
        $pages++;

        // Stylesheet
        $settings = $theme->normalize($snapshot['theme']['settings'] ?? []);
        $this->put($siteDir.'/assets/css/style.css', $this->stylesheets->css($theme, $settings));
        $this->put($siteDir.'/assets/css/theme.css', $this->stylesheets->tokens($theme, $settings));

        // JavaScript (progressive enhancement only)
        if ($options['include_javascript'] ?? true) {
            foreach (['app.js', 'carousel.js', 'search.js'] as $script) {
                $source = public_path('assets/js/'.$script);
                if (is_file($source)) {
                    $this->put($siteDir.'/assets/js/'.$script, (string) file_get_contents($source));
                }
            }
        }

        // Distribution metadata
        if ($options['include_sitemap'] ?? true) {
            $this->put($siteDir.'/sitemap.xml', $this->sitemap($content));
            $this->put($siteDir.'/robots.txt', "User-agent: *\nAllow: /\nSitemap: ".rtrim((string) config('app.url'), '/').'/'.$slug."/sitemap.xml\n");
        }
        $this->put($siteDir.'/README.txt', $this->readme($content, $export));

        $validation = $this->validator->validate($siteDir);
        if ($validation['errors'] !== []) {
            throw new RuntimeException('Export validation failed: '.implode(' | ', array_slice($validation['errors'], 0, 5)));
        }

        return [
            'pages' => $pages,
            'media' => $mediaResult['copied'],
            'warnings' => $validation['warnings'],
            'directory' => $siteDir,
            'links' => $validation['links'],
        ];
    }

    private function put(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write export file [{$path}].");
        }
    }

    private function sitemap(SiteContent $content): string
    {
        $base = rtrim((string) config('app.url'), '/').'/'.$content->encyclopedia['slug'];
        $urls = [$base];

        foreach ($content->topics as $topic) {
            $urls[] = $base.'/topics/'.$topic['slug'];
        }
        foreach ($content->articles as $article) {
            $urls[] = $base.'/articles/'.$article['slug'];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url)."</loc></url>\n";
        }

        return $xml."</urlset>\n";
    }

    private function readme(SiteContent $content, EncyclopediaExport $export): string
    {
        $version = $content->release['version'] ?? 'draft';
        $generated = now()->toDateTimeString();

        return <<<TXT
        {$content->encyclopedia['title']}
        =========================================

        This is a self-contained static website generated by Alarife.

        Release:   v{$version}
        Generated: {$generated}
        Pages:     Homepage, topics, articles, search, 404

        How to read offline
        -------------------
        Extract the archive and open "index.html" in a modern browser. No server,
        database or internet connection is required. All styles, scripts and images
        are included in the "assets" directory.

        Publishing online
        -----------------
        Upload the extracted folder to any static host. Each page is a directory
        containing index.html, so no rewrite rules are needed.

        Search
        ------
        Offline search is powered by "assets/search/search-index.js" and works from
        the file system without network requests.

        Integrity
        ---------
        The SHA-256 checksum of the downloaded ZIP is shown on the export page.

        TXT;
    }
}
