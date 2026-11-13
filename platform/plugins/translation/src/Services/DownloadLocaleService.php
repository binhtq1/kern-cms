<?php

namespace Kern\Translation\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class DownloadLocaleService
{
    public function handle(string $locale): string
    {
        if (! preg_match('/\A[a-zA-Z0-9_-]+\z/', $locale)) {
            throw new InvalidArgumentException('Invalid locale.');
        }
        $file = storage_path('app/locale-'.$locale.'.zip');
        File::ensureDirectoryExists(dirname($file));
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the locale archive.');
        }

        $sources = [lang_path($locale), lang_path($locale.'.json')];
        foreach (File::glob(lang_path('vendor/*/*/'.$locale)) as $path) {
            $sources[] = $path;
        }
        foreach (File::glob(lang_path('vendor/*/*/'.$locale.'.json')) as $path) {
            $sources[] = $path;
        }
        foreach ($sources as $source) {
            if (File::isFile($source)) {
                $zip->addFile($source, substr($source, strlen(lang_path()) + 1));
            } elseif (File::isDirectory($source)) {
                foreach (File::allFiles($source) as $entry) {
                    $zip->addFile($entry->getPathname(), substr($entry->getPathname(), strlen(lang_path()) + 1));
                }
            }
        }
        if (! $zip->close()) {
            throw new RuntimeException('Cannot finish the locale archive.');
        }

        return $file;
    }
}
