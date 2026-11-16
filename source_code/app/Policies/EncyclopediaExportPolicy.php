<?php

namespace App\Policies;

use App\Models\EncyclopediaExport;
use App\Models\User;

class EncyclopediaExportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function view(User $user, EncyclopediaExport $export): bool
    {
        return $export->encyclopedia->isMember($user);
    }

    public function download(User $user, EncyclopediaExport $export): bool
    {
        if ($export->encyclopedia->isMember($user)) {
            return true;
        }

        return $export->encyclopedia->export_enabled
            && $export->publication_id !== null
            && $export->publication?->id === $export->encyclopedia->latestPublication()?->id
            && $export->encyclopedia->isLinkAccessible();
    }

    public function delete(User $user, EncyclopediaExport $export): bool
    {
        return in_array($export->encyclopedia->membershipRole($user), ['owner', 'editor'], true);
    }
}
