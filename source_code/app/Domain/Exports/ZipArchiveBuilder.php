<?php

namespace App\Domain\Exports;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class ZipArchiveBuilder
{
    /**
     * @return array{path: string, size: int, checksum: string}
     */
    public function build(string $siteDir, string $rootName, string $targetPath): array
    {
        File::ensureDirectoryExists(dirname($targetPath));
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the export ZIP archive.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($siteDir, \FilesystemIterator::SKIP_DOTS)
        );

        $count = 0;
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($siteDir) + 1));
            if (str_contains($relative, '..')) {
                throw new RuntimeException('Refusing to package an unsafe path.');
            }
            $zip->addFile($file->getPathname(), $rootName.'/'.$relative);
            $count++;
        }

        if ($count === 0) {
            $zip->close();
            throw new RuntimeException('Nothing was generated for this export.');
        }

        $zip->close();

        if (! is_file($targetPath)) {
            throw new RuntimeException('The export archive was not written.');
        }

        return [
            'path' => $targetPath,
            'size' => (int) filesize($targetPath),
            'checksum' => hash_file('sha256', $targetPath) ?: '',
        ];
    }
}
