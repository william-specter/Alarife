<?php

namespace App\Domain\Themes;

use Illuminate\Support\Facades\Cache;

class ThemeStylesheet
{
    public const VERSION = '2';

    public function css(Theme $theme, array $settings): string
    {
        $base = (string) file_get_contents(resource_path('views/themes/shared/base.css'));
        $themeCss = (string) file_get_contents(resource_path("views/themes/{$theme->key}/theme.css"));
        $cacheKey = 'theme-css:'.$theme->key.':'.$theme->version.':'.md5($base.$themeCss).':'.md5(json_encode($settings)).':'.self::VERSION;

        return Cache::rememberForever($cacheKey, function () use ($theme, $settings, $base, $themeCss) {
            return $base."\n".$themeCss."\n".$this->tokens($theme, $settings);
        });
    }

    public function tokens(Theme $theme, array $settings): string
    {
        $colors = $settings['colors'];
        $type = $settings['typography'];
        $layout = $settings['layout'];
        $contrast = $settings['accessibility']['contrast'] === 'high';

        $tokens = [
            '--site-primary' => $colors['primary'],
            '--site-accent' => $colors['accent'],
            '--site-bg' => $colors['background'],
            '--site-text' => $colors['text'],
            '--site-muted' => $contrast ? $colors['text'] : $this->mix($colors['text'], $colors['background'], 0.62),
            '--site-line' => $this->mix($colors['text'], $colors['background'], 0.16),
            '--site-surface' => $this->mix($colors['text'], $colors['background'], 0.035),
            '--site-surface-strong' => $this->mix($colors['text'], $colors['background'], 0.08),
            '--site-content-width' => $layout['contentWidth'].'px',
            '--site-font-heading' => $theme->fontStack($type['headingFont']),
            '--site-font-body' => $theme->fontStack($type['bodyFont']),
            '--site-base-size' => $type['baseSize'].'px',
            '--site-line-height' => (string) $type['lineHeight'],
            '--site-density' => (string) match ($layout['density']) {
                'compact' => '0.85',
                'spacious' => '1.3',
                default => '1',
            },
            '--site-radius' => $theme->key === 'minimal' ? '0px' : ($theme->key === 'editorial' ? '2px' : '10px'),
        ];

        $css = ":root {\n";
        foreach ($tokens as $name => $value) {
            $css .= "  {$name}: {$value};\n";
        }

        return $css."}\n";
    }

    /** Blend two hex colours; ratio is how much of $a to keep. */
    private function mix(string $a, string $b, float $ratio): string
    {
        $a = $this->rgb($a);
        $b = $this->rgb($b);
        $out = [];
        foreach (['r', 'g', 'b'] as $channel) {
            $out[$channel] = (int) round($a[$channel] * $ratio + $b[$channel] * (1 - $ratio));
        }

        return sprintf('#%02x%02x%02x', $out['r'], $out['g'], $out['b']);
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
