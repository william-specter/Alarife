<?php

namespace App\Domain\Encyclopedias;

use App\Domain\Activity\RecordActivity;
use App\Domain\Content\Slug;
use App\Domain\Themes\ThemeRegistry;
use App\Models\Encyclopedia;
use App\Models\User;

class CreateEncyclopedia
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly RecordActivity $activity,
    ) {}

    public function __invoke(User $user, array $data): Encyclopedia
    {
        $theme = $this->themes->find($data['theme_key'] ?? ThemeRegistry::DEFAULT) ?? $this->themes->default();

        $encyclopedia = Encyclopedia::create([
            'owner_id' => $user->id,
            'title' => $data['title'],
            'slug' => Slug::uniqueFor(Encyclopedia::class, 0, $data['slug'] ?? $data['title']),
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'language' => $data['language'] ?? 'en',
            'visibility' => $data['visibility'] ?? 'private',
            'status' => 'draft',
            'theme_key' => $theme->key,
            'theme_version' => $theme->version,
            'theme_settings' => $theme->defaultSettings(),
            'cover_style' => $data['cover_style'] ?? 'hero',
            'export_enabled' => (bool) ($data['export_enabled'] ?? true),
        ]);

        ($this->activity)($encyclopedia, $user, 'encyclopedia.created', $encyclopedia);

        return $encyclopedia;
    }
}
