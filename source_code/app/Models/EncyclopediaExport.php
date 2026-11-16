<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EncyclopediaExport extends Model
{
    protected $fillable = [
        'encyclopedia_id',
        'publication_id',
        'requested_by',
        'template_key',
        'template_version',
        'renderer_version',
        'options',
        'status',
        'progress',
        'archive_path',
        'archive_size',
        'checksum',
        'cache_key',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'progress' => 'integer',
            'archive_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->archive_path !== null;
    }

    public function archiveExists(): bool
    {
        return $this->archive_path !== null && Storage::disk('local')->exists($this->archive_path);
    }

    public function downloadName(): string
    {
        $base = $this->encyclopedia?->slug ?? 'encyclopedia';
        $version = $this->publication ? '-v'.$this->publication->version : '-draft';

        return $base.$version.'.zip';
    }

    public function sourceLabel(): string
    {
        return $this->publication ? 'Release v'.$this->publication->version : 'Draft';
    }
}
