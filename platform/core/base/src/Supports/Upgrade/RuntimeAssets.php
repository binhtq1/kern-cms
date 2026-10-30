<?php

namespace Kern\Base\Supports\Upgrade;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class RuntimeAssets
{
    public function __construct(private Filesystem $files) {}

    public function sources(): array
    {
        $root = realpath(core_path('..'));
        $sources = [];
        foreach (ServiceProvider::pathsToPublish(null, 'cms-public') as $source => $destination) {
            $real = realpath($source);
            if ($real && str_starts_with($real, $root.DIRECTORY_SEPARATOR) && str_starts_with($destination, public_path('vendor/core/'))) {
                $sources[$source] = $destination;
            }
        }
        $admin = core_path('../public/build/admin');
        if (is_dir($admin)) {
            $sources[$admin] = public_path('build/admin');
        }

        return $sources;
    }

    public function plan(): array
    {
        app(NativeFrontendCompatibility::class)->assertSupported();

        $registry = storage_path('app/kern/runtime-assets.json');
        if (is_link($registry)) {
            throw new RuntimeException('Runtime asset registry must not be a symlink.');
        }
        $previous = is_file($registry) ? json_decode($this->files->get($registry), true, flags: JSON_THROW_ON_ERROR) : [];
        $stock = null;
        $plan = [];
        foreach ($this->sources() as $source => $destination) {
            foreach ($this->files->allFiles($source) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Runtime assets must not contain symlinks.');
                }
                $target = $destination.'/'.$file->getRelativePathname();
                $relative = substr($target, strlen(public_path()) + 1);
                $hash = $this->fingerprint($file->getPathname()) ?? throw new RuntimeException('Missing runtime asset: '.$relative);
                $this->assertDestination($target, $relative);
                $current = $this->fingerprint($target);
                if ($current !== null) {
                    if ($current !== $hash && $current !== ($previous[$relative] ?? null) && ! in_array($current, ($stock ??= $this->stockAssets())[$relative] ?? [], true)) {
                        throw new RuntimeException('Customized CMS asset: '.$relative.'. Move the customization to site assets before upgrading.');
                    }
                }
                $plan[$relative] = ['source' => $file->getPathname(), 'target' => $target, 'hash' => $hash, 'previous_hash' => $current];
            }
        }
        if (! isset($plan['build/admin/manifest.json'])) {
            throw new RuntimeException('The runtime has no compiled admin assets. Build the distribution first.');
        }

        return $plan;
    }

    protected function stockManifestPath(): string
    {
        return core_path('base/resources/upgrade/stock-assets.json');
    }

    /** @return array<string, list<string>> */
    protected function stockAssets(): array
    {
        $stock = [];
        $legacy = json_decode($this->files->get(core_path('base/resources/upgrade/0.1.0.json')), true, flags: JSON_THROW_ON_ERROR)['assets'];
        $releases = [$legacy];
        if (is_file($this->stockManifestPath())) {
            $catalog = json_decode($this->files->get($this->stockManifestPath()), true, flags: JSON_THROW_ON_ERROR);
            if (($catalog['format'] ?? null) !== 1 || ! is_array($catalog['releases'] ?? null)) {
                throw new RuntimeException('Invalid stock runtime asset catalog. Reinstall the runtime package.');
            }
            foreach ($catalog['releases'] as $version => $assets) {
                if (! preg_match('/^\d+\.\d+\.\d+$/D', (string) $version) || ! is_array($assets)) {
                    throw new RuntimeException('Invalid stock runtime asset release. Reinstall the runtime package.');
                }
                $releases[] = $assets;
            }
        }
        foreach ($releases as $assets) {
            foreach ($assets as $path => $hash) {
                if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                    throw new RuntimeException('Invalid stock runtime asset fingerprint. Reinstall the runtime package.');
                }
                $stock[$path][] = $hash;
            }
        }

        return $stock;
    }

    public function backup(array $plan, string $directory): void
    {
        $this->assertUnchanged($plan);
        $inventory = ['format' => 1, 'assets' => [], 'registry_existed' => false];
        foreach ($plan as $relative => $entry) {
            $inventory['assets'][$relative] = $entry['previous_hash'];
            if ($entry['previous_hash'] === null) {
                continue;
            }
            $destination = $directory.'/assets/'.$relative;
            $this->copyBackup($entry['target'], $destination);
            if ($this->fingerprint($destination) !== $entry['previous_hash']) {
                throw new RuntimeException('CMS asset changed while being backed up: '.$relative);
            }
        }
        $registry = storage_path('app/kern/runtime-assets.json');
        if (is_link($registry)) {
            throw new RuntimeException('Runtime asset registry must not be a symlink.');
        }
        if (is_file($registry)) {
            $this->copyBackup($registry, $directory.'/runtime-assets.json');
            $inventory['registry_existed'] = true;
        }
        $inventoryPath = $directory.'/asset-backup.json';
        if ($this->files->put($inventoryPath, json_encode($inventory, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false || ! chmod($inventoryPath, 0600)) {
            throw new RuntimeException('Cannot write the CMS asset backup inventory.');
        }
    }

    private function copyBackup(string $source, string $destination): void
    {
        $this->files->ensureDirectoryExists(dirname($destination), 0700);
        if (! $this->files->copy($source, $destination) || ! chmod($destination, 0600)) {
            throw new RuntimeException('Cannot back up CMS asset: '.$source);
        }
    }

    private function fingerprint(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('Cannot read CMS asset: '.$path);
        }

        return $hash;
    }

    private function assertUnchanged(array $plan): void
    {
        foreach ($plan as $relative => $entry) {
            $this->assertDestination($entry['target'], $relative);
            if (is_link($entry['source']) || $this->fingerprint($entry['target']) !== $entry['previous_hash'] || $this->fingerprint($entry['source']) !== $entry['hash']) {
                throw new RuntimeException('CMS asset changed during the upgrade: '.$relative.'. Run cms:upgrade again after resolving the change.');
            }
        }
    }

    private function assertDestination(string $target, string $relative): void
    {
        if (file_exists($target) && ! is_file($target)) {
            throw new RuntimeException('Asset destination is not a regular file: '.$relative);
        }
        if (is_link($target)) {
            throw new RuntimeException('Refusing to overwrite a symlink: '.$relative);
        }
        $parent = dirname($target);
        while (strlen($parent) > strlen(public_path())) {
            if (file_exists($parent) && ! is_dir($parent)) {
                throw new RuntimeException('Asset parent is not a directory: '.$relative);
            }
            if (is_link($parent)) {
                throw new RuntimeException('Refusing to publish through a symlink: '.$relative);
            }
            $parent = dirname($parent);
        }
    }

    public function publish(array $plan): void
    {
        $this->assertUnchanged($plan);
        foreach ($plan as $relative => $entry) {
            $this->files->ensureDirectoryExists(dirname($entry['target']));
            if (! $this->files->copy($entry['source'], $entry['target'])) {
                throw new RuntimeException('Cannot publish asset: '.$relative);
            }
        }
        $this->record($plan);
    }

    public function record(array $plan): void
    {
        $registry = array_map(fn (array $entry): string => $entry['hash'], $plan);
        $path = storage_path('app/kern/runtime-assets.json');
        $this->files->ensureDirectoryExists(dirname($path), 0700);
        $this->files->replace($path, json_encode($registry, JSON_THROW_ON_ERROR), 0600);
    }
}
