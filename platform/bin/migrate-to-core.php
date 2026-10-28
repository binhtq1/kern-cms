<?php

/** Prepare an installed 0.1.0 website for the Composer-managed runtime. */
$options = getopt('', ['project:', 'check']);
$project = realpath($options['project'] ?? getcwd());
$applied = [];
$directory = null;
$baseline = json_decode(file_get_contents(__DIR__.'/../core/base/resources/upgrade/0.1.0.json'), true, flags: JSON_THROW_ON_ERROR);

try {
    if (! $project || ! is_file($project.'/storage/installed') || ! is_file($project.'/.env')) {
        throw new RuntimeException('Select an installed 0.1.0 website with --project=/absolute/path.');
    }
    $versionPath = __DIR__.'/../core/base/resources/version.json';
    $targetVersion = json_decode(file_get_contents($versionPath), true, flags: JSON_THROW_ON_ERROR)['version'] ?? '';
    if (! is_string($targetVersion) || ! preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $targetVersion)) {
        throw new RuntimeException('Use a built runtime archive with a stable target version.');
    }
    $manifestPath = $project.'/composer.json';
    if (is_link($manifestPath)) {
        throw new RuntimeException('The project composer.json must not be a symlink.');
    }
    $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    if (isset($manifest['require']['binhtq1/kern-core'])) {
        fwrite(STDOUT, "The website already requires binhtq1/kern-core. Run composer update and php artisan cms:upgrade.\n");
        exit(0);
    }
    if (! isset($manifest['require']['kern/platform'])) {
        throw new RuntimeException('This converter supports the original 0.1.0 project layout only.');
    }
    $conflicts = [];
    foreach ($baseline['runtime'] as $relative => $hash) {
        $path = $project.'/'.$relative;
        if (! is_file($path) || is_link($path) || hash_file('sha256', $path) !== $hash) {
            $conflicts[] = $relative;
        }
    }
    foreach (['platform/core', 'platform/packages'] as $directory) {
        $path = $project.'/'.$directory;
        if (! is_dir($path) || is_link($path)) {
            throw new RuntimeException('Missing or symlinked legacy runtime: '.$directory);
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($project) + 1);
            if ($file->isLink() || ($file->isFile() && ! isset($baseline['runtime'][$relative]))) {
                $conflicts[] = $relative;
            }
        }
    }
    foreach ($baseline['skeleton'] as $relative => $hash) {
        $path = $project.'/'.$relative;
        if (! is_file($path) || is_link($path) || hash_file('sha256', $path) !== $hash) {
            $conflicts[] = $relative;
        }
        if (! is_file(__DIR__.'/../core/base/resources/site-template/'.$relative)) {
            throw new RuntimeException('Use the migration tool from the built runtime archive.');
        }
    }
    $memberEntry = 'resources/js/member.tsx';
    $memberTemplate = __DIR__.'/../core/base/resources/site-template/'.$memberEntry;
    $addMemberEntry = ! file_exists($project.'/'.$memberEntry) && is_file($memberTemplate);
    if (is_link($project.'/'.$memberEntry)) {
        $conflicts[] = $memberEntry;
    }
    if ($conflicts) {
        throw new RuntimeException("Legacy core or build configuration customizations detected; no project files were changed. Move these to site code, configuration or overrides first:\n".implode("\n", array_slice(array_unique($conflicts), 0, 30)));
    }
    if (isset($options['check'])) {
        fwrite(STDOUT, "The 0.1.0 website can be converted without replacing site files.\n");
        exit(0);
    }
    $directory = $project.'/storage/kern-conversions/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(6));
    if (! mkdir($directory, 0700, true)) {
        throw new RuntimeException('Cannot create the conversion backup directory.');
    }
    file_put_contents($directory.'/.gitignore', "*\n");
    foreach (['composer.json', 'composer.lock'] as $file) {
        if (is_file($project.'/'.$file)) {
            if (! copy($project.'/'.$file, $directory.'/'.$file)) {
                throw new RuntimeException('Cannot back up '.$file);
            }
            chmod($directory.'/'.$file, 0600);
        }
    }
    foreach (array_keys($baseline['runtime']) as $path) {
        if (str_ends_with($path, '/composer.json')) {
            $module = json_decode(file_get_contents($project.'/'.$path), true, flags: JSON_THROW_ON_ERROR);
            unset($manifest['require'][$module['name']]);
        }
    }
    $manifest['require']['binhtq1/kern-core'] = '^'.$targetVersion;
    ksort($manifest['require']);
    foreach ($manifest['repositories'] ?? [] as $key => $repository) {
        if (($repository['type'] ?? null) === 'path' && in_array($repository['url'] ?? '', ['platform/core', 'platform/packages/*'], true)) {
            unset($manifest['repositories'][$key]);
        }
    }
    if (empty($manifest['repositories'])) {
        unset($manifest['repositories']);
    } elseif (array_is_list(json_decode(file_get_contents($manifestPath), true)['repositories'] ?? [])) {
        $manifest['repositories'] = array_values($manifest['repositories']);
    }
    foreach (['post-update-cmd', 'post-install-cmd'] as $event) {
        if (! isset($manifest['scripts'][$event])) {
            continue;
        }
        $commands = (array) $manifest['scripts'][$event];
        $manifest['scripts'][$event] = array_values(array_filter($commands, fn ($command) => ! in_array($command, ['@php artisan cms:publish:assets', '@php artisan cms:publish:assets --ansi', '@php artisan vendor:publish --tag=laravel-assets --ansi --force'], true)));
    }
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    $updates = ['composer.json' => $encoded];
    foreach (array_keys($baseline['skeleton']) as $relative) {
        $updates[$relative] = file_get_contents(__DIR__.'/../core/base/resources/site-template/'.$relative);
        $backupPath = $directory.'/'.$relative;
        if (! is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0700, true);
        }
        if (! copy($project.'/'.$relative, $backupPath)) {
            throw new RuntimeException('Cannot back up '.$relative);
        }
    }
    if ($addMemberEntry) {
        $updates[$memberEntry] = file_get_contents($memberTemplate);
    }
    foreach ($updates as $relative => $content) {
        $destination = $project.'/'.$relative;
        $temporary = $destination.'.kern-'.bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $content) === false || ! rename($temporary, $destination)) {
            throw new RuntimeException('Cannot replace '.$relative);
        }
        $applied[] = $relative;
    }
    fwrite(STDOUT, "Composer configuration converted. Backup: $directory\nOld runtime files and all site customizations were retained.\nNext: composer update binhtq1/kern-core 'kern/*' --with-all-dependencies\nThen: php artisan cms:upgrade\n");
} catch (Throwable $exception) {
    foreach ($applied as $relative) {
        if (is_file($directory.'/'.$relative)) {
            copy($directory.'/'.$relative, $project.'/'.$relative);
        } else {
            unlink($project.'/'.$relative);
        }
    }
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
