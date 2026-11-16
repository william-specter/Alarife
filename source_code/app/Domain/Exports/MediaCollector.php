<?php

namespace App\Domain\Exports;

use App\Domain\Rendering\SiteContent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaCollector
{
    /**
     * Copy every referenced media asset into the export and return an id → relative path map.
     *
     * @return array{map: array<int, string>, copied: int}
     */
    public function collect(SiteContent $content, string $siteDir): array
    {
        $ids = [];

        foreach ($content->covers as $cover) {
            $ids[] = (int) $cover['media_id'];
        }
        foreach ($content->articles as $article) {
            if (! empty($article['featured_media_id'])) {
                $ids[] = (int) $article['featured_media_id'];
            }
            foreach (SiteContent::mediaIdsInContent($article['content'] ?? []) as $id) {
                $ids[] = (int) $id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        $map = [];
        $copied = 0;

        foreach ($ids as $id) {
            $media = $content->media[$id] ?? null;
            if (! $media || empty($media['path'])) {
                throw new RuntimeException("Media asset #{$id} is referenced but missing from the export snapshot.");
            }

            $disk = $media['disk'] ?? 'public';
            if (! Storage::disk($disk)->exists($media['path'])) {
                throw new RuntimeException("Media file for asset #{$id} does not exist on disk [{$disk}].");
            }

            $ext = pathinfo($media['path'], PATHINFO_EXTENSION) ?: 'bin';
            $base = Str::slug((string) ($media['alt'] ?: pathinfo($media['path'], PATHINFO_FILENAME)));
            $base = $base !== '' ? $base : 'image';
            $relative = "assets/images/{$id}-".substr($base, 0, 60).'.'.$ext;

            $absolute = $siteDir.'/'.$relative;
            @mkdir(dirname($absolute), 0775, true);

            $stream = Storage::disk($disk)->readStream($media['path']);
            if ($stream === false) {
                throw new RuntimeException("Unable to read media asset #{$id}.");
            }
            $target = fopen($absolute, 'wb');
            stream_copy_to_stream($stream, $target);
            fclose($target);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $map[$id] = $relative;
            $copied++;
        }

        return ['map' => $map, 'copied' => $copied];
    }
}
