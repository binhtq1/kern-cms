<?php

namespace Kern\Base\Supports;

use Illuminate\Support\Facades\File;
use ZipArchive;

class Zipper
{
    public function compress(string $src, string $destination): bool
    {
        if (! File::exists($src)) {
            return false;
        }
        File::ensureDirectoryExists(dirname($destination));
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        if (File::isFile($src)) {
            $zip->addFile($src, basename($src));
        } else {
            $this->recurseZip($src, $zip, strlen(rtrim($src, DIRECTORY_SEPARATOR)) + 1);
        }

        return $zip->close();
    }

    public function extract(string $src, string $destination): bool
    {
        File::ensureDirectoryExists($destination);
        $zip = new ZipArchive;
        if ($zip->open($src) !== true) {
            return false;
        }
        try {
            return $zip->extractTo($destination);
        } finally {
            $zip->close();
        }
    }

    public function recurseZip(string $src, ZipArchive $zip, int $pathLength): void
    {
        foreach (File::allFiles($src) as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), $pathLength));
        }
    }
}
