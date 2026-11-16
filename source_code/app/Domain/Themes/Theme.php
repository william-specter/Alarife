<?php

namespace App\Domain\Themes;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class Theme
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly array $defaults,
    ) {}

    public static function fromManifest(array $manifest): self
    {
        return new self(
            key: $manifest['key'],
            name: $manifest['name'],
            version: $manifest['version'],
            description: $manifest['description'] ?? '',
            defaults: $manifest['defaults'] ?? [],
        );
    }

    public function defaultSettings(): array
    {
        return $this->defaults;
    }

    /**
     * Merge and validate author-supplied settings against the theme contract.
     *
     * @throws ValidationException
     */
    public function normalize(?array $input): array
    {
        $settings = $this->deepMerge($this->defaults, $input ?? []);
        $errors = [];

        $unknown = array_diff(array_keys($input ?? []), ['colors', 'typography', 'layout', 'carousel', 'accessibility']);
        foreach ($unknown as $key) {
            $errors["settings.$key"] = "Unknown settings group [$key].";
        }

        $hex = '/^#[0-9a-fA-F]{6}$/';
        foreach (['primary', 'accent', 'background', 'text'] as $key) {
            if (! preg_match($hex, (string) Arr::get($settings, "colors.$key"))) {
                $errors["settings.colors.$key"] = 'Use a six-digit hex colour.';
            }
        }

        foreach (['headingFont', 'bodyFont'] as $key) {
            if (! in_array(Arr::get($settings, "typography.$key"), ['serif', 'sans', 'mono'], true)) {
                $errors["settings.typography.$key"] = 'Choose serif, sans or mono.';
            }
        }

        $baseSize = (int) Arr::get($settings, 'typography.baseSize');
        if ($baseSize < 14 || $baseSize > 22) {
            $errors['settings.typography.baseSize'] = 'Base size must be between 14 and 22 px.';
        }

        $lineHeight = (float) Arr::get($settings, 'typography.lineHeight');
        if ($lineHeight < 1.3 || $lineHeight > 2.0) {
            $errors['settings.typography.lineHeight'] = 'Line height must be between 1.3 and 2.0.';
        }

        $width = (int) Arr::get($settings, 'layout.contentWidth');
        if ($width < 880 || $width > 1440) {
            $errors['settings.layout.contentWidth'] = 'Content width must be between 880 and 1440 px.';
        }

        if (! in_array(Arr::get($settings, 'layout.density'), ['compact', 'comfortable', 'spacious'], true)) {
            $errors['settings.layout.density'] = 'Choose a valid density.';
        }

        if (! is_bool(Arr::get($settings, 'layout.sidebar'))) {
            $errors['settings.layout.sidebar'] = 'Sidebar must be true or false.';
        }

        if (! in_array(Arr::get($settings, 'carousel.transition'), ['fade', 'slide'], true)) {
            $errors['settings.carousel.transition'] = 'Transition must be fade or slide.';
        }

        foreach (['carousel.autoplay', 'carousel.showCaptions', 'accessibility.reduceMotion'] as $key) {
            if (! is_bool(Arr::get($settings, $key))) {
                $errors['settings.'.$key] = 'This option must be true or false.';
            }
        }

        if (! in_array(Arr::get($settings, 'accessibility.contrast'), ['normal', 'high'], true)) {
            $errors['settings.accessibility.contrast'] = 'Contrast must be normal or high.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $settings['typography']['baseSize'] = $baseSize;
        $settings['typography']['lineHeight'] = $lineHeight;
        $settings['layout']['contentWidth'] = $width;

        return $settings;
    }

    public function fontStack(string $font): string
    {
        return match ($font) {
            'serif' => "Georgia, 'Iowan Old Style', 'Palatino Linotype', 'Times New Roman', serif",
            'mono' => "'SFMono-Regular', Consolas, 'Liberation Mono', monospace",
            default => "'Helvetica Neue', Helvetica, Arial, ui-sans-serif, system-ui, sans-serif",
        };
    }

    public function previewImage(): string
    {
        return match ($this->key) {
            'minimal' => 'modern-minimalist.png',
            'editorial' => 'editorial-magazine.png',
            'atlas' => 'Illustrated-atlas.png',
            'nocturne' => 'contemporary-dark.png',
            default => 'classic-reference.png',
        };
    }

    private function deepMerge(array $defaults, array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = $this->deepMerge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
