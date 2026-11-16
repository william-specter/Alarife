<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['encyclopedia_id', 'user_id', 'action', 'subject_type', 'subject_id', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function encyclopedia(): BelongsTo
    {
        return $this->belongsTo(Encyclopedia::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
