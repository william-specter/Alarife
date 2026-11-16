<?php

namespace App\Http\Controllers\Author\Concerns;

use App\Domain\Themes\ThemeRegistry;
use App\Models\ActivityLog;
use App\Models\Encyclopedia;

trait PresentsEncyclopedia
{
    protected function encyclopediaCard(Encyclopedia $encyclopedia): array
    {
        $covers = $encyclopedia->covers->sortBy('position')->values();
        $cover = $covers->first()?->media;

        return [
            'id' => $encyclopedia->id,
            'title' => $encyclopedia->title,
            'subtitle' => $encyclopedia->subtitle,
            'description' => $encyclopedia->description,
            'slug' => $encyclopedia->slug,
            'status' => $encyclopedia->status,
            'visibility' => $encyclopedia->visibility,
            'language' => $encyclopedia->language,
            'theme_key' => $encyclopedia->theme_key,
            'theme_name' => app(ThemeRegistry::class)->find($encyclopedia->theme_key)?->name,
            'cover_style' => $encyclopedia->cover_style,
            'cover_url' => $cover?->url(),
            'covers_count' => $covers->count(),
            'topics_count' => $encyclopedia->topics_count ?? $encyclopedia->topics()->count(),
            'articles_count' => $encyclopedia->articles_count ?? $encyclopedia->articles()->count(),
            'published_articles_count' => $encyclopedia->published_articles_count ?? $encyclopedia->articles()->where('status', 'published')->count(),
            'export_enabled' => $encyclopedia->export_enabled,
            'updated_at' => $encyclopedia->updated_at?->toIso8601String(),
            'published_at' => $encyclopedia->published_at?->toIso8601String(),
            'latest_release' => $encyclopedia->relationLoaded('publications')
                ? $encyclopedia->publications->max('version')
                : $encyclopedia->publications()->max('version'),
        ];
    }

    protected function activityRow(ActivityLog $activity): array
    {
        return [
            'id' => $activity->id,
            'action' => $activity->action,
            'user_name' => $activity->user?->name ?? 'System',
            'encyclopedia_title' => $activity->encyclopedia?->title,
            'subject' => $activity->meta['title'] ?? $activity->meta['version'] ?? null,
            'meta' => $activity->meta,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
