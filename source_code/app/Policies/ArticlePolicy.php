<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function view(User $user, Article $article): bool
    {
        $encyclopedia = $article->encyclopedia;

        return $encyclopedia->isAccessibleBy($user)
            && ($article->isPublished() || $encyclopedia->isMember($user));
    }

    public function update(User $user, Article $article): bool
    {
        return $article->encyclopedia->isMember($user);
    }

    public function delete(User $user, Article $article): bool
    {
        $encyclopedia = $article->encyclopedia;
        $role = $encyclopedia->membershipRole($user);

        if (in_array($role, ['owner', 'editor'], true)) {
            return true;
        }

        return $role === 'author' && $article->created_by === $user->id && $article->status !== 'published';
    }

    public function publish(User $user, Article $article): bool
    {
        return in_array($article->encyclopedia->membershipRole($user), ['owner', 'editor'], true);
    }

    public function duplicate(User $user, Article $article): bool
    {
        return $article->encyclopedia->isMember($user);
    }
}
