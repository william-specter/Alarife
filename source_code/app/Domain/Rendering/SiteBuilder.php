<?php

namespace App\Domain\Rendering;

use App\Domain\Content\ContentHtmlRenderer;
use App\Domain\Content\MediaUrlResolver;
use App\Domain\Themes\ThemeRegistry;
use Illuminate\Validation\ValidationException;

class SiteBuilder
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly ContentHtmlRenderer $renderer,
    ) {}

    public function site(
        SiteContent $content,
        MediaUrlResolver $media,
        callable $link,
        bool $static = false,
        string $assetBase = '',
        ?string $downloadUrl = null,
    ): array {
        $theme = $this->themes->find($content->encyclopedia['theme_key']) ?? $this->themes->default();
        try {
            $settings = $theme->normalize($content->encyclopedia['theme_settings'] ?? []);
        } catch (ValidationException) {
            $settings = $theme->defaultSettings();
        }

        $slug = $content->encyclopedia['slug'];
        $navigation = $this->navigation($content, $link);
        $recent = array_slice($this->sortedArticles($content), 0, 6);

        $covers = [];
        foreach ($content->covers as $cover) {
            $url = $media->url((int) $cover['media_id']);
            if (! $url) {
                continue;
            }
            $covers[] = array_merge($cover, ['url' => $url]);
        }

        return [
            'encyclopedia' => $content->encyclopedia,
            'theme' => [
                'key' => $theme->key,
                'name' => $theme->name,
                'version' => $content->encyclopedia['theme_version'] ?? $theme->version,
                'settings' => $settings,
            ],
            'covers' => $covers,
            'navigation' => $navigation,
            'featured_topics' => array_slice($navigation, 0, 4),
            'recent_articles' => array_map(fn ($article) => $this->card($article, $content, $media, $link), $recent),
            'all_articles' => array_map(fn ($article) => $this->card($article, $content, $media, $link), $this->sortedArticles($content)),
            'counts' => [
                'topics' => count($content->topics),
                'articles' => count($content->articles),
            ],
            'release' => $content->release,
            'cross_references' => $content->crossReferences,
            'static' => $static,
            'asset_base' => $assetBase,
            'link' => $link,
            'home_url' => $link('/'.$slug),
            'search_url' => $link('/'.$slug.'/search'),
            'download_url' => $downloadUrl,
            'canonical' => $link('/'.$slug),
            'year' => date('Y'),
        ];
    }

    public function homePage(SiteContent $content, array $site): array
    {
        $featured = array_map(function (array $topic) use ($content) {
            $topic['lead'] = null;
            $articles = $content->articlesForTopic((int) $topic['id']);

            return $topic;
        }, $site['featured_topics']);

        return [
            'type' => 'home',
            'featured_topics' => $featured,
            'recent_articles' => $site['recent_articles'],
            'all_topics' => $site['navigation'],
        ];
    }

    public function topicPage(SiteContent $content, array $site, string $slug, MediaUrlResolver $media, callable $link): ?array
    {
        $topic = $content->topicBySlug($slug);
        if ($topic === null) {
            return null;
        }

        $parent = $topic['parent_id'] ? $content->topicBySlugById((int) $topic['parent_id']) : null;
        $children = array_values(array_filter($content->topics, fn ($t) => (int) ($t['parent_id'] ?? 0) === (int) $topic['id']));
        $articles = array_map(
            fn ($article) => $this->card($article, $content, $media, $link),
            $content->articlesForTopic((int) $topic['id'])
        );

        return [
            'type' => 'topic',
            'topic' => $topic,
            'parent' => $parent,
            'children' => $children,
            'articles' => $articles,
            'breadcrumbs' => array_values(array_filter([$parent, $topic])),
        ];
    }

    public function articlePage(SiteContent $content, array $site, string $slug, MediaUrlResolver $media, callable $link): ?array
    {
        $article = $content->articleBySlug($slug);
        if ($article === null) {
            return null;
        }

        $body = $this->renderer->render($article['content'], [
            'media' => fn (int $id, ?string $src) => $media->url($id, $src),
            'links' => $link,
        ]);

        $topic = $article['topic_id'] ? $content->topicBySlugById((int) $article['topic_id']) : null;
        $siblings = $topic ? $content->articlesForTopic((int) $topic['id']) : $content->articles;
        $index = array_search($article['id'], array_column($siblings, 'id'), true);
        $prev = $index !== false && $index > 0 ? $siblings[$index - 1] : null;
        $next = $index !== false && $index < count($siblings) - 1 ? $siblings[$index + 1] : null;
        $related = array_slice(array_values(array_filter(
            $siblings,
            fn ($a) => $a['id'] !== $article['id']
        )), 0, 3);

        $featured = null;
        if (! empty($article['featured_media_id'])) {
            $url = $media->url((int) $article['featured_media_id']);
            if ($url) {
                $featured = [
                    'url' => $url,
                    'alt' => $content->media[(int) $article['featured_media_id']]['alt'] ?? '',
                    'caption' => $content->media[(int) $article['featured_media_id']]['caption'] ?? '',
                ];
            }
        }

        return [
            'type' => 'article',
            'article' => $article,
            'body' => $body,
            'topic' => $topic,
            'featured' => $featured,
            'related' => array_map(fn ($a) => $this->card($a, $content, $media, $link), $related),
            'prev' => $prev ? $this->card($prev, $content, $media, $link) : null,
            'next' => $next ? $this->card($next, $content, $media, $link) : null,
            'breadcrumbs' => array_values(array_filter([$topic, $article])),
            'reading_minutes' => $body->readingMinutes(),
        ];
    }

    public function searchPage(SiteContent $content, array $site, string $query, array $results): array
    {
        return [
            'type' => 'search',
            'query' => $query,
            'results' => $results,
        ];
    }

    public function card(array $article, SiteContent $content, MediaUrlResolver $media, callable $link): array
    {
        $topic = $article['topic_id'] ? $content->topicBySlugById((int) $article['topic_id']) : null;
        $image = null;
        if (! empty($article['featured_media_id'])) {
            $image = $media->url((int) $article['featured_media_id']);
        }

        return array_merge($article, [
            'url' => $link('/'.$content->encyclopedia['slug'].'/articles/'.$article['slug']),
            'topic_title' => $topic['title'] ?? null,
            'topic_url' => $topic ? $link('/'.$content->encyclopedia['slug'].'/topics/'.$topic['slug']) : null,
            'image_url' => $image,
            'reading_minutes' => $this->renderer->wordCount($article['content'] ?? []) > 0
                ? $this->renderer->render($article['content'])->readingMinutes()
                : 1,
        ]);
    }

    private function navigation(SiteContent $content, callable $link): array
    {
        $nodes = $content->topicTree();
        $slug = $content->encyclopedia['slug'];

        $decorate = function (array $items) use (&$decorate, $link, $slug): array {
            foreach ($items as &$item) {
                $item['url'] = $link('/'.$slug.'/topics/'.$item['slug']);
                $item['children'] = $decorate($item['children']);
            }

            return $items;
        };

        return $decorate($nodes);
    }

    private function sortedArticles(SiteContent $content): array
    {
        $articles = $content->articles;
        usort($articles, function (array $a, array $b) {
            $dateCompare = strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });

        return $articles;
    }
}
