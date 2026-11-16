<?php

namespace App\Domain\Encyclopedias;

use App\Models\Encyclopedia;
use App\Models\EncyclopediaCover;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoverManager
{
    public function add(Encyclopedia $encyclopedia, MediaAsset $media, array $data = []): EncyclopediaCover
    {
        if ($media->encyclopedia_id !== $encyclopedia->id) {
            throw ValidationException::withMessages(['media_asset_id' => 'Choose an image from this encyclopedia.']);
        }

        return DB::transaction(function () use ($encyclopedia, $media, $data) {
            $count = $encyclopedia->covers()->lockForUpdate()->count();
            if ($count >= 3) {
                throw ValidationException::withMessages(['covers' => 'An encyclopedia can have at most three covers.']);
            }
            if ($encyclopedia->covers()->where('media_asset_id', $media->id)->exists()) {
                throw ValidationException::withMessages(['media_asset_id' => 'This image is already a cover.']);
            }

            return $encyclopedia->covers()->create([
                'media_asset_id' => $media->id,
                'position' => $this->nextPosition($encyclopedia),
                'caption' => $data['caption'] ?? $media->caption,
                'alt_text' => $data['alt_text'] ?? $media->alt_text,
            ]);
        });
    }

    public function update(EncyclopediaCover $cover, array $data): EncyclopediaCover
    {
        $cover->update([
            'caption' => $data['caption'] ?? $cover->caption,
            'alt_text' => $data['alt_text'] ?? $cover->alt_text,
        ]);

        return $cover;
    }

    public function remove(EncyclopediaCover $cover): void
    {
        DB::transaction(function () use ($cover) {
            $encyclopedia = $cover->encyclopedia;
            $cover->delete();
            $this->resequence($encyclopedia);
        });
    }

    /** @param array<int, int> $orderedCoverIds */
    public function reorder(Encyclopedia $encyclopedia, array $orderedCoverIds): void
    {
        $covers = $encyclopedia->covers()->get()->keyBy('id');
        if (count($orderedCoverIds) !== $covers->count()) {
            throw ValidationException::withMessages(['order' => 'Send every cover exactly once.']);
        }

        DB::transaction(function () use ($covers, $orderedCoverIds) {
            foreach ($orderedCoverIds as $index => $id) {
                $cover = $covers->get((int) $id);
                if (! $cover) {
                    throw ValidationException::withMessages(['order' => 'Unknown cover in reorder request.']);
                }
                $cover->update(['position' => $index + 1]);
            }
        });
    }

    private function nextPosition(Encyclopedia $encyclopedia): int
    {
        return ((int) $encyclopedia->covers()->max('position')) + 1;
    }

    private function resequence(Encyclopedia $encyclopedia): void
    {
        $position = 1;
        foreach ($encyclopedia->covers()->orderBy('position')->get() as $cover) {
            $cover->update(['position' => $position++]);
        }
    }
}
