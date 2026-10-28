<?php

namespace Kern\Base\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class DownloadLocaleService
{
    public function handle(string $locale): void
    {
        if (! in_array($locale, $this->getAvailableLocales(), true)) {
            throw new InvalidArgumentException('The locale is not available.');
        }
        if ($locale === 'en' || File::isDirectory(lang_path($locale))) {
            return;
        }

        $dictionary = json_decode(File::get(plugin_path('translation/resources/dictionaries/'.$locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
        File::ensureDirectoryExists(lang_path($locale));
        $this->copyTranslations(lang_path('en'), lang_path($locale), $dictionary);
        File::put(lang_path($locale.'.json'), json_encode($dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        foreach (['core', 'packages', 'plugins'] as $group) {
            foreach (File::directories(platform_path($group)) as $module) {
                $source = $module.'/resources/lang/en';
                if (File::isDirectory($source)) {
                    $this->copyTranslations($source, lang_path('vendor/'.$group.'/'.basename($module).'/'.$locale), $dictionary);
                }
            }
        }
    }

    public function getAvailableLocales(): array
    {
        return array_values(array_unique(['en', ...array_map(
            fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            File::glob(plugin_path('translation/resources/dictionaries/*.json')),
        )]));
    }

    protected function copyTranslations(string $source, string $destination, array $dictionary): void
    {
        if (! File::isDirectory($source)) {
            return;
        }
        foreach (File::allFiles($source) as $file) {
            $target = $destination.'/'.$file->getRelativePathname();
            File::ensureDirectoryExists(dirname($target));
            if ($file->getExtension() !== 'php') {
                File::copy($file->getPathname(), $target);

                continue;
            }
            $translations = File::getRequire($file->getPathname());
            array_walk_recursive($translations, function (mixed &$value) use ($dictionary): void {
                if (is_string($value)) {
                    $value = $dictionary[$value] ?? $value;
                }
            });
            File::put($target, '<?php'.PHP_EOL.PHP_EOL.'return '.var_export($translations, true).';'.PHP_EOL);
        }
    }
}
