<?php

namespace Tests\Concerns;

use App\Models\Article;
use App\Models\Encyclopedia;
use App\Models\MediaAsset;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait CreatesContent
{
    protected function makeEncyclopedia(User $owner, array $overrides = []): Encyclopedia
    {
        return Encyclopedia::create(array_merge([
            'owner_id' => $owner->id,
            'title' => 'Test Encyclopedia',
            'slug' => 'test-encyclopedia-'.uniqid(),
            'subtitle' => 'A test reference',
            'description' => 'A reference work used in tests.',
            'language' => 'en',
            'visibility' => 'private',
            'status' => 'draft',
            'theme_key' => 'classic',
            'theme_version' => '1.0.0',
            'theme_settings' => [],
            'cover_style' => 'hero',
            'export_enabled' => true,
        ], $overrides));
    }

    protected function makeMedia(Encyclopedia $encyclopedia, string $name = 'cover.png'): MediaAsset
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $path = 'media/'.$encyclopedia->id.'/'.$name;
        Storage::disk('public')->put($path, $png);

        return $encyclopedia->media()->create([
            'uploaded_by' => $encyclopedia->owner_id,
            'disk' => 'public',
            'path' => $path,
            'filename' => $name,
            'mime_type' => 'image/png',
            'size' => strlen($png),
            'width' => 1,
            'height' => 1,
            'alt_text' => 'A test image',
            'checksum' => hash('sha256', $png.$name.$encyclopedia->id),
        ]);
    }

    protected function makeTopic(Encyclopedia $encyclopedia, array $overrides = []): Topic
    {
        return $encyclopedia->topics()->create(array_merge([
            'title' => 'Test Topic',
            'slug' => 'test-topic-'.uniqid(),
            'description' => 'A topic used in tests.',
            'status' => 'published',
            'sort_order' => 1,
        ], $overrides));
    }

    protected function makeArticle(Encyclopedia $encyclopedia, ?Topic $topic, array $overrides = []): Article
    {
        return $encyclopedia->articles()->create(array_merge([
            'topic_id' => $topic?->id,
            'title' => 'Test Article',
            'slug' => 'test-article-'.uniqid(),
            'excerpt' => 'A short excerpt.',
            'content' => $this->doc([
                $this->paragraph('An article body with a bold claim.'),
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A section']]],
                $this->paragraph('More text for the reader.'),
            ]),
            'status' => 'draft',
            'sort_order' => 1,
            'revision_number' => 1,
            'created_by' => $encyclopedia->owner_id,
            'updated_by' => $encyclopedia->owner_id,
        ], $overrides));
    }

    protected function doc(array $content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    protected function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
    }
}
