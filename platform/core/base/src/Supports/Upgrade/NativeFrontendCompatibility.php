<?php

namespace Kern\Base\Supports\Upgrade;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class NativeFrontendCompatibility
{
    public function assertSupported(): void
    {
        $paths = [];
        $plugins = json_decode(setting('activated_plugins', '[]'), true) ?: [];
        foreach ($plugins as $plugin) {
            if (is_string($plugin) && preg_match('/^[a-zA-Z0-9_-]+$/', $plugin)) {
                $paths['plugin '.$plugin] = plugin_path($plugin);
            }
        }
        $theme = setting('theme');
        if (is_string($theme) && $theme !== '') {
            $paths['theme '.$theme] = theme_path($theme);
        }
        $this->assertPathsAreSupported($paths);
    }

    /** @param array<string, string> $paths */
    public function assertPathsAreSupported(array $paths): void
    {
        $conflicts = [];
        foreach ($paths as $name => $directory) {
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                if (! $file->isFile() || $file->isLink() || preg_match('~(?:^|/)(?:public|node_modules|vendor|\.git)/~', $relative)) {
                    continue;
                }
                if (! in_array($file->getExtension(), ['php', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'vue', 'html'], true)) {
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                $source = preg_replace('~/\*.*?\*/|<!--.*?-->|^[\t ]*//[^\r\n]*~ms', '', $source);
                if (preg_match('~\bjQuery\s*[.(]|(?<![\w$])\$\s*(?:\(|\.(?:ajax|ajaxSetup|get|post|extend|fn|validator)\b)|[\'"]jquery[\'"]\s*[,\]\)]|(?:src|href)\s*=\s*[\'"][^\'"]*jquery~i', $source)) {
                    $conflicts[] = $name.': '.$relative;
                }
            }
        }
        if ($conflicts) {
            throw new RuntimeException("Site-owned frontend code still requires jQuery. Update these plugins/themes to their native JavaScript or React versions before upgrading or publishing runtime assets. Core will not overwrite your customizations:\n".implode("\n", array_slice($conflicts, 0, 30)));
        }
    }
}
