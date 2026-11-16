<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncyclopediaMember extends Model
{
    protected $fillable = ['encyclopedia_id', 'user_id', 'role'];

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
