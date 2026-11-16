<?php

namespace App\Domain\Media;

use App\Domain\Activity\RecordActivity;
use App\Domain\Rendering\SiteContent;
use App\Models\Encyclopedia;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreMedia
{
    public const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly RecordActivity $activity) {}

    public function __invoke(Encyclopedia $encyclopedia, User $user, UploadedFile $file, array $meta = []): MediaAsset
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED, true)) {
            throw ValidationException::withMessages(['file' => 'Only JPEG, PNG, WebP or AVIF images are allowed.']);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Images must be 10 MB or smaller.']);
        }

        $dimensions = @getimagesize($file->getRealPath());
        if (! $dimensions || $dimensions[0] < 200 || $dimensions[1] < 200) {
            throw ValidationException::withMessages(['file' => 'Images must be at least 200×200 pixels.']);
        }
        if ($dimensions[0] > 8000 || $dimensions[1] > 8000) {
            throw ValidationException::withMessages(['file' => 'Images must be at most 8000×8000 pixels.']);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $existing = $encyclopedia->media()->where('checksum', $checksum)->first();
        if ($existing) {
            return $existing;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'jpg',
        };

        $path = $file->storeAs(
            'media/'.$encyclopedia->id,
            (string) Str::ulid().'.'.$extension,
            'public'
        );

        $media = MediaAsset::create([
            'encyclopedia_id' => $encyclopedia->id,
            'uploaded_by' => $user->id,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'alt_text' => $meta['alt_text'] ?? null,
            'caption' => $meta['caption'] ?? null,
            'checksum' => $checksum,
        ]);

        ($this->activity)($encyclopedia, $user, 'media.uploaded', $media, ['name' => $media->filename]);

        return $media;
    }

    public function delete(MediaAsset $media, User $user): void
    {
        $blockers = [];

        if ($media->encyclopedia->covers()->where('media_asset_id', $media->id)->exists()) {
            $blockers[] = 'used as an encyclopedia cover';
        }

        $featured = $media->encyclopedia->articles()->where('featured_image_id', $media->id)->pluck('title');
        if ($featured->isNotEmpty()) {
            $blockers[] = 'featured image for: '.$featured->take(3)->implode(', ');
        }

        $inline = $media->encyclopedia->articles()->get()
            ->filter(fn ($article) => in_array($media->id, SiteContent::mediaIdsInContent($article->content ?? []), true))
            ->pluck('title');
        if ($inline->isNotEmpty()) {
            $blockers[] = 'used inside: '.$inline->take(3)->implode(', ');
        }

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'media' => 'This image cannot be deleted because it is '.implode('; ', $blockers).'.',
            ]);
        }

        Storage::disk($media->disk)->delete($media->path);
        ($this->activity)($media->encyclopedia, $user, 'media.deleted', $media, ['name' => $media->filename]);
        $media->delete();
    }
}
