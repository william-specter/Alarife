<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossReference extends Model
{
    protected $fillable = [
        'source_encyclopedia_id',
        'target_encyclopedia_id',
        'target_type',
        'target_id',
        'note',
        'sort_order',
    ];

    public function sourceEncyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class, 'source_encyclopedia_id');
    }

    public function targetEncyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class, 'target_encyclopedia_id');
    }

    public function target(): Article|Topic|null
    {
        return $this->target_type === 'article'
            ? Article::find($this->target_id)
            : Topic::find($this->target_id);
    }
}
