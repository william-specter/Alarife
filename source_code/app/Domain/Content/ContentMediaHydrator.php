<?php

namespace App\Domain\Content;

use App\Models\MediaAsset;
use Illuminate\Support\Collection;

class ContentMediaHydrator
{
    /**
     * Attach current media URLs to image and gallery nodes for the authoring canvas.
     *
     * @param  Collection<int, MediaAsset>  $media
     */
    public function hydrate(?array $doc, Collection $media): ?array
    {
        if (! is_array($doc)) {
            return $doc;
        }

        $urls = $media->mapWithKeys(fn ($asset) => [$asset->id => $asset->url()])->all();

        $walk = function (array $node) use (&$walk, $urls): array {
            $type = $node['type'] ?? null;

            if ($type === 'image' && isset($node['attrs'])) {
                $id = (int) ($node['attrs']['mediaId'] ?? 0);
                if ($id > 0 && isset($urls[$id])) {
                    $node['attrs']['src'] = $urls[$id];
                }
            }

            if ($type === 'gallery' && isset($node['attrs']['images']) && is_array($node['attrs']['images'])) {
                foreach ($node['attrs']['images'] as $index => $image) {
                    $id = (int) ($image['mediaId'] ?? 0);
                    if ($id > 0 && isset($urls[$id])) {
                        $node['attrs']['images'][$index]['src'] = $urls[$id];
                    }
                }
            }

            foreach ($node['content'] ?? [] as $index => $child) {
                if (is_array($child)) {
                    $node['content'][$index] = $walk($child);
                }
            }

            return $node;
        };

        return $walk($doc);
    }
}
