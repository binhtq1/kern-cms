<?php

namespace Kern\Theme\Supports;

use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Kern\Theme\Facades\Theme;

class ThemeVite
{
    public function render(): HtmlString
    {
        $activeTheme = Theme::getThemeName();
        $parentTheme = Theme::getInheritTheme();
        $tags = [];

        if ($parentTheme && $parentTheme !== $activeTheme) {
            $tags[] = $this->renderTheme($parentTheme, $parentTheme);
        }

        if ($activeTheme !== '') {
            $tags[] = $this->renderTheme($activeTheme, Theme::getPublicThemeName());
        }

        return new HtmlString(implode('', $tags));
    }

    private function renderTheme(string $theme, string $publicTheme): string
    {

        if ($theme === '' || ! preg_match('/^[a-z0-9_-]+$/i', $theme)) {
            return '';
        }

        $metadataPath = theme_path($theme.'/theme.json');

        if (! is_file($metadataPath)) {
            return '';
        }

        $metadata = json_decode(file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        if (! array_key_exists('vite', $metadata)) {
            return '';
        }

        $entries = $metadata['vite']['entrypoints'] ?? null;

        if (! is_array($entries) || $entries === []) {
            throw new InvalidArgumentException("Theme [$theme] must declare at least one Vite entrypoint.");
        }

        foreach ($entries as $entry) {
            if (! is_string($entry) || ! preg_match('#^assets/(?!.*(?:^|/)\.\.?/)[a-zA-Z0-9_./-]+\.(?:js|ts|jsx|tsx|css|scss|sass)$#', $entry)) {
                throw new InvalidArgumentException("Theme [$theme] has an invalid Vite entrypoint.");
            }
        }

        $vite = (clone ViteFacade::getFacadeRoot())
            ->useHotFile(public_path('themes/'.$theme.'.hot'))
            ->useBuildDirectory('themes/'.$publicTheme.'/build');

        return $vite(array_map(fn (string $entry): string => 'platform/themes/'.$theme.'/'.$entry, $entries))->toHtml();
    }
}
