<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    protected $fillable = [
        'encyclopedia_id',
        'version',
        'snapshot_path',
        'theme_key',
        'theme_version',
        'theme_settings',
        'topic_count',
        'article_count',
        'published_by',
        'notes',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'theme_settings' => 'array',
            'version' => 'integer',
            'topic_count' => 'integer',
            'article_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(EncyclopediaExport::class);
    }

    public function publicArchive(): ?EncyclopediaExport
    {
        return $this->exports()
            ->where('status', 'completed')
            ->where('cache_key', 'like', 'public:%')
            ->latest('completed_at')
            ->first();
    }
}
