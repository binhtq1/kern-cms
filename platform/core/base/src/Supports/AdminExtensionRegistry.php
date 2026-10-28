<?php

namespace Kern\Base\Supports;

class AdminExtensionRegistry
{
    public function supports(string $plugin, string $module): bool
    {
        $modules = config("core.base.admin.extensions.$plugin.modules", []);
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $plugin) || ! is_array($modules) || ! in_array($module, $modules, true)) {
            return false;
        }

        $assetKey = 'kern.admin.assets.'.public_path();
        $assetReason = request()->attributes->has($assetKey)
            ? request()->attributes->get($assetKey)
            : app(AdminRenderer::class)->assetFallbackReason();
        if ($assetReason !== null) {
            return false;
        }

        $root = base_path("platform/plugins/$plugin/resources/js/admin");
        $registrationPath = $root.'/registration.json';
        $bundlePath = public_path('build/admin/admin-registry.json');
        if (! is_file($registrationPath) || ! is_file($bundlePath)) {
            return false;
        }

        $manifestPath = public_path('build/admin/manifest.json');
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        $registration = json_decode(file_get_contents($registrationPath), true);
        $bundle = json_decode(file_get_contents($bundlePath), true);
        $entry = $bundle['packages']["plugins/$plugin"] ?? null;
        if (($registration['version'] ?? null) !== 1 || ($bundle['version'] ?? null) !== 1 || ! is_array($registration['namespaces'] ?? null) || ! $registration['namespaces'] || ! is_array($entry) || ($entry['hash'] ?? null) !== hash_file('sha256', $registrationPath) || ! is_array($entry['pages'] ?? null) || ! $entry['pages']) {
            return false;
        }

        foreach ($registration['namespaces'] as $namespace => $directory) {
            if (! preg_match('/^[A-Z][A-Za-z0-9]*$/D', $namespace) || ! is_string($directory) || ($directory !== '' && ! preg_match('/^[A-Za-z][A-Za-z0-9-]*(?:\/[A-Za-z][A-Za-z0-9-]*)*$/D', $directory))) {
                return false;
            }
        }

        foreach ($entry['pages'] as $page) {
            if (! is_string($page) || ! preg_match('/^([A-Z][A-Za-z0-9]*)\/([A-Za-z][A-Za-z0-9-]*(?:\/[A-Za-z][A-Za-z0-9-]*)*)$/D', $page, $matches) || ! array_key_exists($matches[1], $registration['namespaces'])) {
                return false;
            }
            $directory = $registration['namespaces'][$matches[1]];
            $source = "platform/plugins/$plugin/resources/js/admin".($directory !== '' ? '/'.$directory : '').'/'.$matches[2].'.tsx';
            if (! is_file(base_path($source)) || (! is_file(public_path('admin.hot')) && ! is_array($manifest[$source] ?? null))) {
                return false;
            }
        }

        return true;
    }
}
