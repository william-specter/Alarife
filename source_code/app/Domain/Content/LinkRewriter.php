<?php

namespace App\Domain\Content;

class LinkRewriter
{
    /** Rewrites site-absolute links to relative paths for a static export. */
    public static function forExport(string $encyclopediaSlug, string $base): callable
    {
        return function (string $href) use ($encyclopediaSlug, $base): string {
            if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
                return $href;
            }

            $path = $href;
            if (preg_match('#^https?://#i', $path)) {
                $parsed = parse_url($path);
                $host = $parsed['host'] ?? '';
                $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
                if ($host !== $appHost) {
                    return $href;
                }
                $path = ($parsed['path'] ?? '/').(isset($parsed['query']) ? '?'.$parsed['query'] : '').(isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
            }

            if (! str_starts_with($path, '/')) {
                return $href;
            }

            [$clean, $suffix] = array_pad(preg_split('/(?=[?#])/', $path, 2), 2, '');
            $segments = array_values(array_filter(explode('/', $clean), fn ($s) => $s !== ''));
            if (($segments[0] ?? null) === $encyclopediaSlug) {
                array_shift($segments);
            }

            if ($segments === []) {
                return $base.'index.html'.$suffix;
            }

            if ($segments[0] === 'search') {
                return $base.'search/index.html'.$suffix;
            }

            if ($segments[0] === 'themes') {
                return $base.'assets/css/style.css'.$suffix;
            }

            return $base.implode('/', $segments).'/index.html'.$suffix;
        };
    }

    public static function identity(): callable
    {
        return fn (string $href): string => $href;
    }
}
