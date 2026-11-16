<?php

namespace App\Domain\Rendering;

use App\Domain\References\CrossReferenceRegistry;
use App\Models\Encyclopedia;

class SiteContent
{
    public function __construct(
        public readonly array $encyclopedia,
        public readonly array $covers,
        public readonly array $topics,
        public readonly array $articles,
        public readonly array $media,
        public readonly ?array $release = null,
        public readonly array $crossReferences = [],
    ) {}

    public static function fromModels(Encyclopedia $encyclopedia, bool $includeDrafts = false, bool $publishedOnly = true): self
    {
        $encyclopedia->loadMissing(['covers.media', 'topics', 'articles.featuredImage', 'articles.author']);

        $topics = $encyclopedia->topics
            ->when($publishedOnly, fn ($collection) => $collection->where('status', 'published'))
            ->sortBy([['sort_order', 'asc'], ['title', 'asc']])
            ->values();

        $articles = $encyclopedia->articles
            ->when($includeDrafts === false, fn ($collection) => $collection->where('status', 'published'))
            ->sortBy([['sort_order', 'asc'], ['published_at', 'desc']])
            ->values();

        $media = [];
        $library = $encyclopedia->media()->get()->keyBy('id');

        foreach ($encyclopedia->covers as $cover) {
            if ($cover->media) {
                $media[$cover->media->id] = self::mediaRow($cover->media);
            }
        }
        foreach ($articles as $article) {
            if ($article->featuredImage) {
                $media[$article->featuredImage->id] = self::mediaRow($article->featuredImage);
            }
            foreach (self::mediaIdsInContent($article->content ?? []) as $id) {
                $asset = $library->get($id);
                if ($asset) {
                    $media[$asset->id] = self::mediaRow($asset);
                }
            }
        }

        return new self(
            encyclopedia: [
                'id' => $encyclopedia->id,
                'title' => $encyclopedia->title,
                'subtitle' => $encyclopedia->subtitle,
                'slug' => $encyclopedia->slug,
                'description' => $encyclopedia->description,
                'language' => $encyclopedia->language,
                'visibility' => $encyclopedia->visibility,
                'status' => $encyclopedia->status,
                'cover_style' => $encyclopedia->cover_style,
                'theme_key' => $encyclopedia->theme_key,
                'theme_version' => $encyclopedia->theme_version,
                'theme_settings' => $encyclopedia->theme_settings ?? [],
                'export_enabled' => (bool) $encyclopedia->export_enabled,
                'published_at' => $encyclopedia->published_at?->toIso8601String(),
            ],
            covers: $encyclopedia->covers->map(fn ($cover) => [
                'media_id' => $cover->media_asset_id,
                'alt' => $cover->alt_text ?: ($cover->media?->alt_text ?? ''),
                'caption' => $cover->caption ?? '',
                'position' => $cover->position,
            ])->values()->all(),
            topics: $topics->map(fn ($topic) => [
                'id' => $topic->id,
                'parent_id' => $topic->parent_id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'description' => $topic->description,
                'sort_order' => $topic->sort_order,
                'status' => $topic->status,
            ])->all(),
            articles: $articles->map(fn ($article) => [
                'id' => $article->id,
                'topic_id' => $article->topic_id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'content_schema_version' => $article->content_schema_version,
                'featured_media_id' => $article->featured_image_id,
                'status' => $article->status,
                'sort_order' => $article->sort_order,
                'revision_number' => $article->revision_number,
                'published_at' => $article->published_at?->toIso8601String(),
                'author' => $article->author?->name,
            ])->all(),
            media: $media,
            release: null,
            crossReferences: app(CrossReferenceRegistry::class)->forReader($encyclopedia),
        );
    }

    public static function fromSnapshot(array $snapshot): self
    {
        $media = [];
        foreach ($snapshot['media'] ?? [] as $item) {
            $media[(int) $item['id']] = $item;
        }

        return new self(
            encyclopedia: $snapshot['encyclopedia'],
            covers: $snapshot['covers'] ?? [],
            topics: $snapshot['topics'] ?? [],
            articles: $snapshot['articles'] ?? [],
            media: $media,
            release: $snapshot['release'] ?? null,
            crossReferences: $snapshot['cross_references'] ?? [],
        );
    }

    public function articleBySlug(string $slug): ?array
    {
        foreach ($this->articles as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    public function topicBySlug(string $slug): ?array
    {
        foreach ($this->topics as $topic) {
            if ($topic['slug'] === $slug) {
                return $topic;
            }
        }

        return null;
    }

    public function topicBySlugById(int $id): ?array
    {
        foreach ($this->topics as $topic) {
            if ((int) $topic['id'] === $id) {
                return $topic;
            }
        }

        return null;
    }

    public function topicTree(): array
    {
        $byParent = [];
        foreach ($this->topics as $topic) {
            $byParent[$topic['parent_id'] ?? 0][] = $topic;
        }

        $build = function (int $parentId) use (&$build, $byParent): array {
            $nodes = [];
            foreach ($byParent[$parentId] ?? [] as $topic) {
                $topic['children'] = $build((int) $topic['id']);
                $topic['article_count'] = count(array_filter(
                    $this->articles,
                    fn ($article) => (int) $article['topic_id'] === (int) $topic['id']
                ));
                $nodes[] = $topic;
            }

            return $nodes;
        };

        return $build(0);
    }

    public function articlesForTopic(int $topicId): array
    {
        return array_values(array_filter(
            $this->articles,
            fn ($article) => (int) $article['topic_id'] === $topicId
        ));
    }

    public static function mediaRow($media): array
    {
        return [
            'id' => $media->id,
            'path' => $media->path,
            'disk' => $media->disk,
            'mime_type' => $media->mime_type,
            'alt' => $media->alt_text,
            'caption' => $media->caption,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    /** @return array<int, int> */
    public static function mediaIdsInContent(array $doc): array
    {
        $ids = [];
        $walk = function (array $node) use (&$walk, &$ids) {
            if (($node['type'] ?? null) === 'image' && isset($node['attrs']['mediaId'])) {
                $ids[] = (int) $node['attrs']['mediaId'];
            }
            if (($node['type'] ?? null) === 'gallery') {
                foreach ($node['attrs']['images'] ?? [] as $image) {
                    if (isset($image['mediaId'])) {
                        $ids[] = (int) $image['mediaId'];
                    }
                }
            }
            foreach ($node['content'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($doc);

        return array_values(array_unique(array_filter($ids)));
    }
}
