<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRedirect extends Model
{
    protected $fillable = ['encyclopedia_id', 'from_slug', 'to_article_id'];

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'to_article_id');
    }
}
