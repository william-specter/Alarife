<?php

namespace App\Domain\Encyclopedias;

use App\Domain\Activity\RecordActivity;
use App\Domain\Content\Slug;
use App\Domain\Rendering\SiteContent;
use App\Models\Article;
use App\Models\Encyclopedia;
use App\Models\MediaAsset;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DuplicateEncyclopedia
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function __invoke(Encyclopedia $source, User $user): Encyclopedia
    {
        $source->load(['covers.media', 'topics', 'articles.featuredImage', 'media']);

        return DB::transaction(function () use ($source, $user) {
            $copy = Encyclopedia::create([
                'owner_id' => $user->id,
                'title' => $source->title.' (copy)',
                'slug' => Slug::uniqueFor(Encyclopedia::class, 0, $source->slug.'-copy'),
                'subtitle' => $source->subtitle,
                'description' => $source->description,
                'language' => $source->language,
                'visibility' => 'private',
                'status' => 'draft',
                'theme_key' => $source->theme_key,
                'theme_version' => $source->theme_version,
                'theme_settings' => $source->theme_settings,
                'cover_style' => $source->cover_style,
                'export_enabled' => $source->export_enabled,
            ]);

            $mediaMap = [];
            $usedMediaIds = collect();

            foreach ($source->covers as $cover) {
                $usedMediaIds->push($cover->media_asset_id);
            }
            foreach ($source->articles as $article) {
                if ($article->featured_image_id) {
                    $usedMediaIds->push($article->featured_image_id);
                }
                $usedMediaIds = $usedMediaIds->merge(SiteContent::mediaIdsInContent($article->content ?? []));
            }

            foreach ($usedMediaIds->unique()->filter() as $mediaId) {
                $media = $source->media->firstWhere('id', $mediaId);
                if (! $media) {
                    continue;
                }
                $mediaMap[$media->id] = $this->copyMedia($media, $copy, $user);
            }

            $topicMap = [];
            foreach ($source->topics->whereNull('parent_id') as $topic) {
                $newTopic = $copy->topics()->create([
                    'title' => $topic->title,
                    'slug' => Slug::uniqueFor(Topic::class, $copy->id, $topic->slug),
                    'description' => $topic->description,
                    'sort_order' => $topic->sort_order,
                    'status' => $topic->status,
                ]);
                $topicMap[$topic->id] = $newTopic->id;
            }
            foreach ($source->topics->whereNotNull('parent_id') as $topic) {
                $newTopic = $copy->topics()->create([
                    'parent_id' => $topicMap[$topic->parent_id] ?? null,
                    'title' => $topic->title,
                    'slug' => Slug::uniqueFor(Topic::class, $copy->id, $topic->slug),
                    'description' => $topic->description,
                    'sort_order' => $topic->sort_order,
                    'status' => $topic->status,
                ]);
                $topicMap[$topic->id] = $newTopic->id;
            }

            foreach ($source->articles as $article) {
                $copy->articles()->create([
                    'topic_id' => $topicMap[$article->topic_id] ?? null,
                    'title' => $article->title,
                    'slug' => Slug::uniqueFor(Article::class, $copy->id, $article->slug),
                    'excerpt' => $article->excerpt,
                    'content' => $this->remapContent($article->content ?? [], $mediaMap),
                    'content_schema_version' => $article->content_schema_version,
                    'featured_image_id' => $mediaMap[$article->featured_image_id] ?? null,
                    'status' => 'draft',
                    'sort_order' => $article->sort_order,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            $position = 1;
            foreach ($source->covers as $cover) {
                if (! isset($mediaMap[$cover->media_asset_id])) {
                    continue;
                }
                $copy->covers()->create([
                    'media_asset_id' => $mediaMap[$cover->media_asset_id],
                    'position' => $position++,
                    'caption' => $cover->caption,
                    'alt_text' => $cover->alt_text,
                ]);
            }

            ($this->activity)($copy, $user, 'encyclopedia.duplicated', $copy, ['source' => $source->title]);

            return $copy;
        });
    }

    private function copyMedia(MediaAsset $media, Encyclopedia $copy, User $user): int
    {
        $extension = $media->extension();
        $path = 'media/'.$copy->id.'/'.Str::ulid().'.'.$extension;
        if (Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->copy($media->path, $path);
        }

        $new = MediaAsset::create([
            'encyclopedia_id' => $copy->id,
            'uploaded_by' => $user->id,
            'disk' => $media->disk,
            'path' => $path,
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'checksum' => hash('sha256', $media->checksum.$copy->id.microtime()),
        ]);

        return $new->id;
    }

    private function remapContent(array $doc, array $map): array
    {
        $walk = function (array $node) use (&$walk, $map): array {
            if (($node['type'] ?? null) === 'image' && isset($node['attrs']['mediaId'])) {
                $old = (int) $node['attrs']['mediaId'];
                $node['attrs']['mediaId'] = $map[$old] ?? null;
            }
            if (($node['type'] ?? null) === 'gallery') {
                foreach ($node['attrs']['images'] ?? [] as $i => $image) {
                    $old = (int) ($image['mediaId'] ?? 0);
                    $node['attrs']['images'][$i]['mediaId'] = $map[$old] ?? null;
                }
            }
            foreach ($node['content'] ?? [] as $i => $child) {
                if (is_array($child)) {
                    $node['content'][$i] = $walk($child);
                }
            }

            return $node;
        };

        return $walk($doc);
    }
}
