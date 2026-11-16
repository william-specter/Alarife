<?php

namespace App\Domain\Activity;

use App\Models\ActivityLog;
use App\Models\Encyclopedia;
use App\Models\User;

class RecordActivity
{
    public function __invoke(
        ?Encyclopedia $encyclopedia,
        ?User $user,
        string $action,
        ?object $subject = null,
        array $meta = [],
    ): ActivityLog {
        return ActivityLog::create([
            'encyclopedia_id' => $encyclopedia?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
