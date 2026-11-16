<?php

namespace App\Domain\Publishing;

use App\Domain\Content\ContentValidator;
use App\Domain\References\CrossReferenceRegistry;
use App\Domain\Rendering\SiteContent;
use App\Domain\Themes\ThemeRegistry;
use App\Models\Encyclopedia;
use App\Models\User;

class SnapshotBuilder
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly ContentValidator $validator,
        private readonly CrossReferenceRegistry $references,
    ) {}

    public function build(Encyclopedia $encyclopedia, int $version, ?User $user, ?string $notes = null): array
    {
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: false);

        foreach ($content->articles as $article) {
            $this->validator->validate($article['content'] ?? ['type' => 'doc', 'content' => []]);
        }

        $media = [];
        foreach ($content->media as $id => $asset) {
            $media[] = [
                'id' => $id,
                'path' => $asset['path'],
                'disk' => $asset['disk'] ?? 'public',
                'mime_type' => $asset['mime_type'],
                'alt' => $asset['alt'] ?? '',
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
            ];
        }

        $theme = $this->themes->findOrFail($content->encyclopedia['theme_key']);
        $settings = $theme->normalize($content->encyclopedia['theme_settings'] ?? []);

        return [
            'snapshot_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'release' => [
                'version' => $version,
                'notes' => $notes,
                'published_at' => now()->toIso8601String(),
                'published_by' => $user?->name,
            ],
            'encyclopedia' => $content->encyclopedia,
            'theme' => [
                'key' => $theme->key,
                'version' => $theme->version,
                'settings' => $settings,
                'cover_style' => $content->encyclopedia['cover_style'],
            ],
            'covers' => $content->covers,
            'topics' => $content->topics,
            'articles' => $content->articles,
            'media' => $media,
            'cross_references' => $this->references->snapshot($encyclopedia),
            'counts' => [
                'topics' => count($content->topics),
                'articles' => count($content->articles),
            ],
        ];
    }

    public function buildDraft(Encyclopedia $encyclopedia): array
    {
        $content = SiteContent::fromModels($encyclopedia, includeDrafts: true, publishedOnly: false);

        $media = [];
        foreach ($content->media as $id => $asset) {
            $media[] = [
                'id' => $id,
                'path' => $asset['path'],
                'disk' => $asset['disk'] ?? 'public',
                'mime_type' => $asset['mime_type'],
                'alt' => $asset['alt'] ?? '',
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
            ];
        }

        $theme = $this->themes->findOrFail($content->encyclopedia['theme_key']);
        $settings = $theme->normalize($content->encyclopedia['theme_settings'] ?? []);

        return [
            'snapshot_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'release' => null,
            'encyclopedia' => $content->encyclopedia,
            'theme' => [
                'key' => $theme->key,
                'version' => $theme->version,
                'settings' => $settings,
                'cover_style' => $content->encyclopedia['cover_style'],
            ],
            'covers' => $content->covers,
            'topics' => $content->topics,
            'articles' => $content->articles,
            'media' => $media,
            'cross_references' => $this->references->forReader($encyclopedia),
            'counts' => [
                'topics' => count($content->topics),
                'articles' => count($content->articles),
            ],
        ];
    }
}
