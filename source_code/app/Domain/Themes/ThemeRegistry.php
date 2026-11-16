<?php

namespace App\Domain\Themes;

use Illuminate\Support\Collection;
use RuntimeException;

class ThemeRegistry
{
    /** @var array<string, Theme>|null */
    private static ?array $themes = null;

    public const DEFAULT = 'classic';

    /** @return Collection<int, Theme> */
    public function all(): Collection
    {
        return collect($this->loaded())->sortBy('name')->values();
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect($this->loaded())->mapWithKeys(fn (Theme $theme) => [$theme->key => $theme->name])->all();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->loaded());
    }

    public function find(string $key): ?Theme
    {
        return $this->loaded()[$key] ?? null;
    }

    public function findOrFail(string $key): Theme
    {
        return $this->find($key) ?? throw new RuntimeException("Unknown theme [$key].");
    }

    public function default(): Theme
    {
        return $this->findOrFail(self::DEFAULT);
    }

    /** @return array<string, Theme> */
    private function loaded(): array
    {
        if (self::$themes !== null) {
            return self::$themes;
        }

        $themes = [];
        foreach (glob(resource_path('views/themes/*/theme.json')) ?: [] as $path) {
            $manifest = json_decode((string) file_get_contents($path), true);
            if (! is_array($manifest) || ! isset($manifest['key'])) {
                continue;
            }

            $theme = Theme::fromManifest($manifest);
            $themes[$theme->key] = $theme;
        }

        return self::$themes = $themes;
    }
}
