<?php

namespace App\Policies;

use App\Models\Topic;
use App\Models\User;

class TopicPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function view(User $user, Topic $topic): bool
    {
        return $topic->encyclopedia->isAccessibleBy($user);
    }

    public function create(User $user, Topic $topic): bool
    {
        return false;
    }

    public function update(User $user, Topic $topic): bool
    {
        return in_array($topic->encyclopedia->membershipRole($user), ['owner', 'editor'], true);
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $this->update($user, $topic);
    }
}
