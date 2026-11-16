<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncyclopediaCover extends Model
{
    protected $fillable = [
        'encyclopedia_id',
        'media_asset_id',
        'position',
        'caption',
        'alt_text',
    ];

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
