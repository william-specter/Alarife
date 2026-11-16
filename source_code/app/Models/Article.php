<?php

namespace App\Models;

use App\Domain\Content\ContentHtmlRenderer;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'encyclopedia_id',
        'topic_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'content_schema_version',
        'featured_image_id',
        'status',
        'sort_order',
        'revision_number',
        'created_by',
        'updated_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'content_schema_version' => 'integer',
            'sort_order' => 'integer',
            'revision_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            $article->content ??= ['type' => 'doc', 'content' => []];
        });
    }

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_image_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->orderByDesc('revision_number');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function wordCount(): int
    {
        return app(ContentHtmlRenderer::class)->wordCount($this->content ?? []);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil($this->wordCount() / 220));
    }
}
