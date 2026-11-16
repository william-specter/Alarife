<?php

namespace App\Domain\Exports;

class LinkValidator
{
    /**
     * Validate generated HTML files for broken internal links, missing assets and remote references.
     *
     * @return array{errors: array<int, string>, warnings: array<int, string>, pages: int, links: int}
     */
    public function validate(string $siteDir): array
    {
        $errors = [];
        $warnings = [];
        $pages = 0;
        $links = 0;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($siteDir));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.html')) {
                continue;
            }

            $pages++;
            $html = (string) file_get_contents($file->getPathname());
            $relativeFile = ltrim(str_replace($siteDir, '', $file->getPathname()), '/');

            if (preg_match('/<(script|link|img)[^>]+(src|href)=["\']https?:\/\//i', $html, $m)) {
                $errors[] = "{$relativeFile}: remote asset reference ({$m[0]}).";
            }
            if (str_contains($html, '@import url(http')) {
                $errors[] = "{$relativeFile}: remote CSS import.";
            }

            if (preg_match_all('/(?:href|src)=["\']([^"\']+)["\']/i', $html, $matches)) {
                foreach ($matches[1] as $target) {
                    $target = html_entity_decode($target, ENT_QUOTES | ENT_HTML5);
                    if ($target === '' || str_starts_with($target, '#')
                        || preg_match('#^(https?:|mailto:|tel:|data:|javascript:)#i', $target)) {
                        continue;
                    }

                    $links++;
                    $clean = preg_split('/[?#]/', $target, 2)[0];
                    if ($clean === '') {
                        continue;
                    }

                    $path = $clean[0] === '/'
                        ? $siteDir.$clean
                        : dirname($file->getPathname()).'/'.$clean;
                    $path = $this->normalize($path);

                    if (str_ends_with($path, '/')) {
                        $path .= 'index.html';
                    }
                    if (is_dir($path)) {
                        $path = rtrim($path, '/').'/index.html';
                    }

                    if (! file_exists($path)) {
                        $errors[] = "{$relativeFile}: broken link or asset [{$target}].";
                    }
                }
            }
        }

        if ($pages === 0) {
            $errors[] = 'No HTML pages were generated.';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'pages' => $pages,
            'links' => $links,
        ];
    }

    private function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }
}
