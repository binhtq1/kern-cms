<?php

/** Test fixture only: deliberately update unmodified stock frontend files. */
$mode = $argv[1] ?? '';
$project = realpath($argv[2] ?? '');
$baselinePath = $argv[3] ?? '';
$archivePath = $argv[4] ?? '';
$roots = ['platform/plugins/', 'platform/themes/', 'public/vendor/core/plugins/', 'public/themes/'];
$buildFiles = ['package.json', 'package-lock.json', 'vite.config.ts', 'tsconfig.json', 'eslint.config.mjs', 'scripts/public-assets.mjs', 'resources/js/admin.tsx', 'resources/js/member.tsx'];
$allowed = static function (string $path) use ($roots, $buildFiles): bool {
    foreach ($roots as $root) {
        if (str_starts_with($path, $root)) {
            return true;
        }
    }

    return in_array($path, $buildFiles, true);
};
$assertPath = static function (string $relative) use ($project): void {
    if (str_starts_with($relative, '/') || str_contains($relative, '\\') || array_intersect(explode('/', $relative), ['', '.', '..'])) {
        throw new RuntimeException('Unsafe stock fixture path: '.$relative);
    }
    $path = $project;
    foreach (explode('/', $relative) as $part) {
        if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
            throw new RuntimeException('Refusing a symlinked fixture path: '.$relative);
        }
        $path .= '/'.$part;
    }
    if (is_link($path) || (file_exists($path) && ! is_file($path))) {
        throw new RuntimeException('Not a regular fixture file: '.$relative);
    }
};
$applied = [];
$backup = null;
try {
    if (! $project || ! is_file($project.'/storage/installed') || ! in_array($mode, ['capture', 'apply'], true)) {
        throw new RuntimeException('Use capture|apply /installed-disposable-project /baseline.json [candidate-starter.zip].');
    }
    if ($mode === 'capture') {
        if (file_exists($baselinePath)) {
            throw new RuntimeException('Do not overwrite a pristine stock baseline.');
        }
        $files = [];
        foreach ($roots as $root) {
            if (! is_dir($project.'/'.$root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project.'/'.$root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($project) + 1);
                $assertPath($relative);
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
        }
        foreach ($buildFiles as $relative) {
            if (is_file($project.'/'.$relative)) {
                $assertPath($relative);
                $files[$relative] = hash_file('sha256', $project.'/'.$relative);
            }
        }
        ksort($files);
        file_put_contents($baselinePath, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        echo 'Captured '.count($files)." pristine stock frontend files.\n";
        exit(0);
    }
    $baseline = json_decode(file_get_contents($baselinePath), true, flags: JSON_THROW_ON_ERROR);
    $zip = new ZipArchive;
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Cannot open the candidate starter archive.');
    }
    $package = json_decode($zip->getFromName('composer.json') ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    if (($package['name'] ?? null) !== 'binhtq1/kern-cms' || ($package['type'] ?? null) !== 'project') {
        throw new RuntimeException('Use the candidate Kern CMS starter archive, not a runtime archive.');
    }
    $candidate = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if (str_ends_with($name, '/') || ! $allowed($name)) {
            continue;
        }
        $assertPath($name);
        $zip->getExternalAttributesIndex($index, $system, $attributes);
        if (($attributes >> 16 & 0170000) === 0120000) {
            throw new RuntimeException('Symlink in candidate stock files: '.$name);
        }
        $candidate[$name] = hash('sha256', $zip->getFromIndex($index));
    }
    $plan = [];
    $conflicts = [];
    foreach (array_unique([...array_keys($baseline), ...array_keys($candidate)]) as $relative) {
        if (! $allowed($relative)) {
            throw new RuntimeException('Unexpected baseline path: '.$relative);
        }
        $assertPath($relative);
        $previous = $baseline[$relative] ?? null;
        $next = $candidate[$relative] ?? null;
        $current = is_file($project.'/'.$relative) ? hash_file('sha256', $project.'/'.$relative) : null;
        if ($next === $previous || $current === $next) {
            continue;
        }
        if ($current !== $previous) {
            $conflicts[] = $relative;

            continue;
        }
        $plan[$relative] = $next;
    }
    if ($conflicts) {
        throw new RuntimeException("Customized stock frontend files conflict with the candidate; nothing was changed:\n".implode("\n", $conflicts));
    }
    $assertPath('storage/frontend-fixture-backups/.probe');
    $backup = $project.'/storage/frontend-fixture-backups/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(6));
    mkdir($backup, 0700, true);
    file_put_contents($backup.'/.gitignore', "*\n");
    foreach ($plan as $relative => $hash) {
        $target = $project.'/'.$relative;
        if (is_file($target)) {
            if (! is_dir(dirname($backup.'/'.$relative))) {
                mkdir(dirname($backup.'/'.$relative), 0700, true);
            }
            if (! copy($target, $backup.'/'.$relative)) {
                throw new RuntimeException('Cannot back up stock file: '.$relative);
            }
            chmod($backup.'/'.$relative, 0600);
        }
    }
    foreach ($plan as $relative => $hash) {
        $target = $project.'/'.$relative;
        if ($hash === null) {
            if (! unlink($target)) {
                throw new RuntimeException('Cannot retire unmodified stock file: '.$relative);
            }
        } else {
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            $temporary = $target.'.fixture-'.bin2hex(random_bytes(4));
            if (file_put_contents($temporary, $zip->getFromName($relative)) === false || ! rename($temporary, $target)) {
                throw new RuntimeException('Cannot update stock file: '.$relative);
            }
        }
        $applied[] = $relative;
    }
    foreach ($plan as $relative => $hash) {
        $actual = is_file($project.'/'.$relative) ? hash_file('sha256', $project.'/'.$relative) : null;
        if ($actual !== $hash) {
            throw new RuntimeException('Stock fixture verification failed: '.$relative);
        }
    }
    file_put_contents($backup.'/applied.json', json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo 'Deliberately updated '.count($plan).' stock source/build/published frontend files. Backup: '.$backup."\n";
} catch (Throwable $error) {
    foreach (array_reverse($applied) as $relative) {
        if (is_file($backup.'/'.$relative)) {
            copy($backup.'/'.$relative, $project.'/'.$relative);
        } elseif (is_file($project.'/'.$relative)) {
            unlink($project.'/'.$relative);
        }
    }
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
