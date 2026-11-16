<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function view(User $user, MediaAsset $media): bool
    {
        return $media->encyclopedia->isMember($user);
    }

    public function create(User $user, MediaAsset $media): bool
    {
        return false;
    }

    public function update(User $user, MediaAsset $media): bool
    {
        return $media->encyclopedia->isMember($user);
    }

    public function delete(User $user, MediaAsset $media): bool
    {
        return $media->encyclopedia->isMember($user);
    }
}
