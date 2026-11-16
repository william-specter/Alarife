<?php

namespace App\Policies;

use App\Models\Encyclopedia;
use App\Models\User;

class EncyclopediaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function view(User $user, Encyclopedia $encyclopedia): bool
    {
        return $encyclopedia->isAccessibleBy($user);
    }

    public function update(User $user, Encyclopedia $encyclopedia): bool
    {
        return in_array($encyclopedia->membershipRole($user), ['owner', 'editor'], true);
    }

    public function manageCovers(User $user, Encyclopedia $encyclopedia): bool
    {
        return $this->update($user, $encyclopedia);
    }

    public function manageContent(User $user, Encyclopedia $encyclopedia): bool
    {
        return $this->update($user, $encyclopedia);
    }

    public function write(User $user, Encyclopedia $encyclopedia): bool
    {
        return in_array($encyclopedia->membershipRole($user), ['owner', 'editor', 'author'], true);
    }

    public function publish(User $user, Encyclopedia $encyclopedia): bool
    {
        return $this->update($user, $encyclopedia);
    }

    public function export(User $user, Encyclopedia $encyclopedia): bool
    {
        return $this->write($user, $encyclopedia);
    }

    public function manageMembers(User $user, Encyclopedia $encyclopedia): bool
    {
        return $encyclopedia->membershipRole($user) === 'owner';
    }

    public function delete(User $user, Encyclopedia $encyclopedia): bool
    {
        return $encyclopedia->membershipRole($user) === 'owner';
    }
}
