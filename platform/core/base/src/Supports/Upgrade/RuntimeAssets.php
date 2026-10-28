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
        $legacy = json_decode($this->files->get(core_path('base/resources/upgrade/0.1.0.json')), true, flags: JSON_THROW_ON_ERROR)['assets'];
        $previous = is_file($registry) ? json_decode($this->files->get($registry), true, flags: JSON_THROW_ON_ERROR) : $legacy;
        $plan = [];
        foreach ($this->sources() as $source => $destination) {
            foreach ($this->files->allFiles($source) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Runtime assets must not contain symlinks.');
                }
                $target = $destination.'/'.$file->getRelativePathname();
                $relative = substr($target, strlen(public_path()) + 1);
                $hash = hash_file('sha256', $file->getPathname());
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
                if (is_file($target)) {
                    $current = hash_file('sha256', $target);
                    if ($current !== $hash && $current !== ($previous[$relative] ?? null)) {
                        throw new RuntimeException('Customized CMS asset: '.$relative.'. Move the customization to site assets before upgrading.');
                    }
                }
                $plan[$relative] = ['source' => $file->getPathname(), 'target' => $target, 'hash' => $hash];
            }
        }
        if (! isset($plan['build/admin/manifest.json'])) {
            throw new RuntimeException('The runtime has no compiled admin assets. Build the distribution first.');
        }

        return $plan;
    }

    public function publish(array $plan): void
    {
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
