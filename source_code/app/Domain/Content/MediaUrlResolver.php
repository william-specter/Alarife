<?php

namespace App\Domain\Content;

use App\Models\MediaAsset;

class MediaUrlResolver
{
    /** @var array<int, ?MediaAsset> */
    private array $cache = [];

    /** @var array<int, string>|null  media id => relative export path */
    private ?array $exportMap = null;

    public static function dynamic(): self
    {
        return new self;
    }

    /**
     * @param  array<int, string>  $map  media id => relative path inside the export
     */
    public static function forExport(array $map): self
    {
        $resolver = new self;
        $resolver->exportMap = $map;

        return $resolver;
    }

    public function url(int $mediaId, ?string $fallback = null): ?string
    {
        if ($mediaId <= 0) {
            return $fallback;
        }

        if ($this->exportMap !== null) {
            return $this->exportMap[$mediaId] ?? null;
        }

        if (! array_key_exists($mediaId, $this->cache)) {
            $this->cache[$mediaId] = MediaAsset::find($mediaId);
        }

        return $this->cache[$mediaId]?->url() ?? $fallback;
    }
}
