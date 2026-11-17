<?php

use App\Models\Encyclopedia;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('encyclopedia.{encyclopediaId}', function ($user, int $encyclopediaId) {
    $encyclopedia = Encyclopedia::find($encyclopediaId);

    if (! $encyclopedia) {
        return false;
    }

    return $user->is_platform_admin || $encyclopedia->isMember($user);
});

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
