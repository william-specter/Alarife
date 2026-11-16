<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'editor_id',
        'revision_number',
        'title',
        'excerpt',
        'content',
        'word_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'revision_number' => 'integer',
            'word_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
