<?php

namespace App\Domain\Activity;

use App\Domain\Realtime\Realtime;
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
        $log = ActivityLog::create([
            'encyclopedia_id' => $encyclopedia?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id,
            'meta' => $meta,
            'created_at' => now(),
        ]);

        $payload = [
            'action' => $action,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'subject' => $meta['title'] ?? $meta['version'] ?? null,
            'encyclopedia_id' => $encyclopedia?->id,
            'encyclopedia_title' => $encyclopedia?->title,
            'user_name' => $user?->name,
            'meta' => $meta,
        ];

        $realtime = app(Realtime::class);
        $realtime->encyclopedia($encyclopedia, 'activity.created', $payload);
        $realtime->user($user, 'activity.created', $payload);

        return $log;
    }
}
