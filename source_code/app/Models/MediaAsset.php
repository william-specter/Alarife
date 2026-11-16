<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $fillable = [
        'encyclopedia_id',
        'uploaded_by',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'caption',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function extension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION) ?: 'jpg';
    }
}
