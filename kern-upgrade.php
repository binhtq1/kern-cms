<?php

/** Standalone release asset. No application or Composer autoloader is needed to start it. */

namespace KernUpgrade;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final class Upgrader
{
    private const RELEASE = '__KERN_UPGRADE_RELEASE__';

    private const FRONTEND_ROOTS = ['platform/plugins/', 'platform/themes/', 'public/vendor/core/', 'public/themes/', 'public/build/admin/'];

    private const BUILD_FILES = ['package.json', 'package-lock.json', 'vite.config.ts', 'tsconfig.json', 'eslint.config.mjs', 'scripts/public-assets.mjs', 'resources/js/admin.tsx', 'resources/js/member.tsx'];

    private ?string $workspace = null;

    private ?string $backup = null;

    private ?string $project = null;

    private bool $maintenanceStarted = false;

    private bool $backupComplete = false;

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $lock = null;
        try {
            $options = $this->options($arguments);
            if (isset($options['help'])) {
                echo "Usage: php kern-upgrade.php --to=0.3.1 [--project=/website] [--check]\n";
                echo "--check checks file conflicts and known custom jQuery usage without changing the website or database.\n";
                echo "--artifacts=/directory reads release archives locally, with the same SHA-256 checks.\n";
                echo "Backups include code, vendor, .env and uploads; .git, node_modules and earlier upgrade backups are excluded.\n";
                echo "Symlink targets are recorded without following them. Conflicting customizations stop the upgrade.\n";
                echo "Run from an installed 0.1.0 website. Pause background writers during deployment.\n";

                return 0;
            }
            $release = json_decode(base64_decode(self::RELEASE, true) ?: '{}', true, flags: JSON_THROW_ON_ERROR);
            if (! isset($release['version'], $release['hashes'], $release['baseline'])) {
                throw new RuntimeException('Download the built kern-upgrade.php asset from a Kern CMS release.');
            }
            if (($options['to'] ?? '') !== $release['version']) {
                throw new RuntimeException('This verified upgrader targets '.$release['version'].'. Pass --to='.$release['version'].'.');
            }
            if (PHP_VERSION_ID < 80400 || ! class_exists(ZipArchive::class) || ! function_exists('proc_open')) {
                throw new RuntimeException('PHP 8.4, ext-zip and proc_open are required.');
            }
            $project = realpath($options['project'] ?? getcwd());
            if (! $project || $project === DIRECTORY_SEPARATOR) {
                throw new RuntimeException('Select an installed website with --project=/absolute/path.');
            }
            $this->project = $project;
            foreach (['.env', 'artisan', 'composer.json', 'composer.lock', 'storage/installed', 'vendor/autoload.php', 'bootstrap/app.php'] as $path) {
                self::safePath($project, $path);
                if (! is_file($project.'/'.$path)) {
                    throw new RuntimeException('Missing installed website file: '.$path);
                }
            }
            if (getenv('COMPOSER') && getenv('COMPOSER') !== 'composer.json') {
                throw new RuntimeException('Unset COMPOSER before upgrading; the website composer.json must be used.');
            }
            $manifest = self::json($project.'/composer.json');
            if (($manifest['config']['vendor-dir'] ?? 'vendor') !== 'vendor') {
                throw new RuntimeException('The 0.1.0 upgrader requires the standard vendor directory.');
            }
            if ($this->alreadyUpgraded($release['version'])) {
                echo 'Kern CMS '.$release['version']." is already installed. Nothing changed.\n";

                return 0;
            }
            if (! isset($manifest['require']['kern/platform']) || isset($manifest['require']['binhtq1/kern-core'])) {
                throw new RuntimeException('This tool supports the original installed 0.1.0 layout. For split runtime websites use Composer and cms:upgrade.');
            }
            $this->workspace = sys_get_temp_dir().'/kern-upgrade-'.bin2hex(random_bytes(12));
            self::directory($this->workspace);
            $archives = [];
            foreach (['kern-cms-0.1.0.zip' => $release['baseline'], ...$release['hashes']] as $name => $hash) {
                if (! preg_match('/^kern-(?:cms|core)-\d+\.\d+\.\d+\.zip$/', $name) || ! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                    throw new RuntimeException('Invalid embedded release metadata.');
                }
                $local = isset($options['artifacts']) ? $options['artifacts'].'/'.$name : null;
                $destination = $this->workspace.'/'.$name;
                if ($local && is_file($local)) {
                    if (! copy($local, $destination)) {
                        throw new RuntimeException('Cannot read local artifact: '.$name);
                    }
                } else {
                    preg_match('/^(kern-(?:cms|core))-(\d+\.\d+\.\d+)\.zip$/', $name, $matches);
                    $this->download('https://github.com/binhtq1/'.$matches[1].'/releases/download/v'.$matches[2].'/'.$name, $destination);
                }
                if (! hash_equals($hash, hash_file('sha256', $destination))) {
                    throw new RuntimeException('SHA-256 mismatch: '.$name.'. No website files were changed.');
                }
                $archives[$name] = self::archive($destination);
            }
            $version = $release['version'];
            $baseline = $archives['kern-cms-0.1.0.zip'];
            $candidate = $archives['kern-cms-'.$version.'.zip'];
            $runtime = $archives['kern-core-'.$version.'.zip'];
            $this->validatePackages($candidate, $runtime, $version);
            foreach ($runtime as $path => $entry) {
                if (str_starts_with($path, 'public/build/admin/')) {
                    $candidate[$path] = $entry;
                }
            }
            $plan = self::plan($project, $baseline, $candidate);
            $runtimeRoot = $this->workspace.'/runtime';
            self::extract($runtime, $runtimeRoot);
            $this->command([PHP_BINARY, $runtimeRoot.'/bin/migrate-to-core.php', '--project='.$project, '--check']);
            $this->command(['composer', '--version', '--no-interaction']);
            echo 'Kern CMS 0.1.0 -> '.$version.': '.count($plan)." stock frontend/build files to update.\n";
            if (isset($options['check'])) {
                echo "Check passed. Website files and database were not changed. Database snapshot and application checks run during the upgrade.\n";

                return 0;
            }
            self::safePath($project, 'storage/framework/kern-upgrade-tool.lock');
            $lock = fopen($project.'/storage/framework/kern-upgrade-tool.lock', 'c');
            if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another standalone upgrade is running.');
            }
            chmod($project.'/storage/framework/kern-upgrade-tool.lock', 0600);
            if ($this->alreadyUpgraded($version)) {
                echo 'Kern CMS '.$version." is already installed. Nothing changed.\n";

                return 0;
            }
            $this->backup = $project.'/storage/kern-upgrader/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(6));
            self::safePath($project, 'storage/kern-upgrader');
            self::directory($this->backup);
            self::write($this->backup.'/.gitignore', "*\n");
            $this->status('backing_up', $version);
            $helper = $this->snapshotHelper($runtimeRoot);
            $this->command([PHP_BINARY, $helper, $project, $this->backup, 'inspect']);
            $information = self::json($this->backup.'/database-info.json');
            if (! $information['maintenance']) {
                $this->command([PHP_BINARY, 'artisan', 'down', '--retry=60', '--no-interaction']);
                $this->maintenanceStarted = true;
            }
            echo 'Backing up website files to '.$this->backup."...\n";
            self::backupWebsite($project, $this->backup, $this->maintenanceStarted);
            $this->command([PHP_BINARY, $helper, $project, $this->backup, 'snapshot']);
            $this->backupComplete = true;
            $this->status('backed_up', $version);
            echo 'Backup: '.$this->backup."\n";
            foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear'] as $command) {
                $this->command([PHP_BINARY, 'artisan', $command, '--no-interaction']);
            }
            $this->command([PHP_BINARY, $runtimeRoot.'/bin/migrate-to-core.php', '--project='.$project]);
            $this->status('upgrading', $version);
            $this->command(['composer', 'update', 'binhtq1/kern-core:'.$version, 'kern/*', '--with-all-dependencies', '--prefer-dist', '--no-interaction', '--no-scripts']);
            self::apply($project, $plan, $candidate);
            $this->command(['composer', 'dump-autoload', '--no-interaction']);
            $this->command([PHP_BINARY, 'artisan', 'cms:upgrade', '--check', '--no-interaction']);
            $this->command([PHP_BINARY, 'artisan', 'cms:upgrade', '--no-interaction']);
            $state = self::json($project.'/storage/app/kern/upgrade.json');
            if (($state['version'] ?? null) !== $version || ! isset($state['completed_at'])) {
                throw new RuntimeException('The CMS did not record a completed upgrade.');
            }
            if ($this->maintenanceStarted) {
                $this->command([PHP_BINARY, 'artisan', 'up', '--no-interaction']);
                $this->maintenanceStarted = false;
            }
            $this->status('completed', $version);
            echo 'Kern CMS '.$version.' upgraded successfully. Backup retained at '.$this->backup."\n";

            return 0;
        } catch (Throwable $error) {
            if ($this->backup) {
                try {
                    $this->status('failed', $release['version'] ?? null);
                } catch (Throwable) {
                    fwrite(STDERR, "Could not record failure status. Check available disk space.\n");
                }
                fwrite(STDERR, $this->backupComplete
                    ? 'Backup retained at '.$this->backup.". Restore matching code and database together if needed.\n"
                    : 'Backup incomplete at '.$this->backup.". Website code was not upgraded.\n");
            }
            if ($this->maintenanceStarted) {
                fwrite(STDERR, "Maintenance mode remains enabled. Resolve the failure or restore the backup before bringing the website up.\n");
            }
            fwrite(STDERR, $error->getMessage()."\n");

            return 1;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            if ($this->workspace) {
                self::removeDirectory($this->workspace);
            }
        }
    }

    /** @param list<string> $arguments
     * @return array<string, string|true>
     */
    private function options(array $arguments): array
    {
        $options = [];
        foreach (array_slice($arguments, 1) as $argument) {
            if (in_array($argument, ['--check', '--help'], true)) {
                $options[substr($argument, 2)] = true;
            } elseif (preg_match('/^--(to|project|artifacts)=(.+)$/', $argument, $matches)) {
                if (isset($options[$matches[1]])) {
                    throw new RuntimeException('Duplicate option: '.$matches[1]);
                }
                $options[$matches[1]] = $matches[2];
            } else {
                throw new RuntimeException('Unknown option: '.$argument.'. Use --help.');
            }
        }

        return $options;
    }

    /** @return array<string, array{hash: string, index: int, archive: string}> */
    public static function archive(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open release archive.');
        }
        $files = [];
        $seen = [];
        $size = 0;
        try {
            if ($zip->numFiles > 50000) {
                throw new RuntimeException('Too many release archive entries.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'];
                self::relativePath(rtrim($name, '/'));
                if (isset($seen[$name])) {
                    throw new RuntimeException('Duplicate release archive path: '.$name);
                }
                $seen[$name] = true;
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000) {
                    throw new RuntimeException('Symlink in release archive: '.$name);
                }
                $size += $entry['size'];
                if ($entry['size'] > 134217728 || $size > 1610612736) {
                    throw new RuntimeException('Release archive is too large.');
                }
                if (! str_ends_with($name, '/')) {
                    $contents = $zip->getFromIndex($index);
                    if ($contents === false) {
                        throw new RuntimeException('Cannot read archive entry: '.$name);
                    }
                    $files[$name] = ['hash' => hash('sha256', $contents), 'index' => $index, 'archive' => $path];
                }
            }
        } finally {
            $zip->close();
        }

        return $files;
    }

    /** @param array<string, array{hash: string, index: int, archive: string}> $baseline
     * @param  array<string, array{hash: string, index: int, archive: string}>  $candidate
     * @return array<string, array{before: ?string, after: ?string}>
     */
    public static function plan(string $project, array $baseline, array $candidate): array
    {
        $plan = [];
        $conflicts = [];
        foreach (array_unique([...array_keys($baseline), ...array_keys($candidate)]) as $path) {
            if (! self::managedFrontend($path)) {
                continue;
            }
            $before = $baseline[$path]['hash'] ?? null;
            $after = $candidate[$path]['hash'] ?? null;
            if ($before === $after) {
                continue;
            }
            self::safePath($project, $path);
            $target = $project.'/'.$path;
            $current = is_file($target) ? hash_file('sha256', $target) : null;
            if ($current === $after) {
                continue;
            }
            if ($current !== $before) {
                $conflicts[] = $path;
            } else {
                $plan[$path] = ['before' => $before, 'after' => $after];
            }
        }
        if ($conflicts) {
            throw new RuntimeException("Customized stock files conflict with this release. No website files were changed:\n".implode("\n", $conflicts));
        }
        self::checkCustomFrontend($project, $candidate, $plan);

        return $plan;
    }

    /** @param array<string, array{hash: string, index: int, archive: string}> $candidate
     * @param  array<string, array{before: ?string, after: ?string}>  $plan
     */
    private static function checkCustomFrontend(string $project, array $candidate, array $plan): void
    {
        $conflicts = [];
        foreach (['platform/plugins', 'platform/themes'] as $root) {
            if (! is_dir($project.'/'.$root)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project.'/'.$root, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $relative = substr($file->getPathname(), strlen($project) + 1);
                if (! $file->isFile() || $file->isLink() || isset($plan[$relative]) || preg_match('~/(?:public|node_modules|vendor|\.git)/~', $relative) || ! in_array($file->getExtension(), ['php', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'vue', 'html'], true)) {
                    continue;
                }
                if (($candidate[$relative]['hash'] ?? null) === hash_file('sha256', $file->getPathname())) {
                    continue;
                }
                $source = preg_replace('~/\*.*?\*/|<!--.*?-->|^[\t ]*//[^\r\n]*~ms', '', file_get_contents($file->getPathname()));
                if (preg_match('~\bjQuery\s*[.(]|(?<![\w$])\$\s*(?:\(|\.(?:ajax|ajaxSetup|get|post|extend|fn|validator)\b)|[\'"]jquery[\'"]\s*[,\]\)]|(?:src|href)\s*=\s*[\'"][^\'"]*jquery~i', $source)) {
                    $conflicts[] = $relative;
                }
            }
        }
        if ($conflicts) {
            throw new RuntimeException("Custom plugin/theme source still requires jQuery. Review these files before upgrading; no website files were changed:\n".implode("\n", $conflicts));
        }
    }

    private static function managedFrontend(string $path): bool
    {
        foreach (self::FRONTEND_ROOTS as $root) {
            if (str_starts_with($path, $root)) {
                return true;
            }
        }

        return in_array($path, self::BUILD_FILES, true);
    }

    /** @param array<string, array{before: ?string, after: ?string}> $plan
     * @param  array<string, array{hash: string, index: int, archive: string}>  $candidate
     */
    public static function apply(string $project, array $plan, array $candidate): void
    {
        foreach ($plan as $path => $entry) {
            self::safePath($project, $path);
            $destination = $project.'/'.$path;
            $current = is_file($destination) ? hash_file('sha256', $destination) : null;
            if ($current !== $entry['before'] && $current !== $entry['after']) {
                throw new RuntimeException('A file changed after preflight. Upgrade stopped: '.$path);
            }
        }
        foreach ($plan as $path => $entry) {
            $destination = $project.'/'.$path;
            if ($entry['after'] === null) {
                if (is_file($destination) && ! unlink($destination)) {
                    throw new RuntimeException('Cannot retire stock file: '.$path);
                }
            } else {
                self::write($destination, self::contents($candidate[$path]), 0644);
            }
        }
    }

    public static function safePath(string $root, string $relative): void
    {
        self::relativePath($relative);
        $path = $root;
        $parts = explode('/', $relative);
        foreach ($parts as $index => $part) {
            if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
                throw new RuntimeException('Unsafe parent path: '.$relative);
            }
            $path .= '/'.$part;
            if (is_link($path)) {
                throw new RuntimeException('Refusing a symlink: '.$relative);
            }
            if ($index === count($parts) - 1 && file_exists($path) && ! is_file($path) && ! is_dir($path)) {
                throw new RuntimeException('Not a regular path: '.$relative);
            }
        }
        if (file_exists($path) && is_dir($path) && ! in_array($relative, ['storage/kern-upgrader'], true)) {
            throw new RuntimeException('Expected a regular file: '.$relative);
        }
    }

    private static function relativePath(string $path): void
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0") || str_contains($path, ':') || str_starts_with($path, '/') || array_intersect(explode('/', $path), ['', '.', '..'])) {
            throw new RuntimeException('Unsafe archive or website path: '.$path);
        }
    }

    /** @param array{archive: string, index: int, hash: string} $entry */
    private static function contents(array $entry): string
    {
        $zip = new ZipArchive;
        if ($zip->open($entry['archive']) !== true) {
            throw new RuntimeException('Cannot open verified archive.');
        }
        try {
            $contents = $zip->getFromIndex($entry['index']);
            if ($contents === false || ! hash_equals($entry['hash'], hash('sha256', $contents))) {
                throw new RuntimeException('Verified archive changed during upgrade.');
            }

            return $contents;
        } finally {
            $zip->close();
        }
    }

    /** @param array<string, array{hash: string, index: int, archive: string}> $files */
    private static function extract(array $files, string $directory): void
    {
        self::directory($directory);
        $zip = new ZipArchive;
        if (! $files || $zip->open(reset($files)['archive']) !== true) {
            throw new RuntimeException('Cannot extract the verified runtime archive.');
        }
        try {
            foreach ($files as $path => $entry) {
                self::safePath($directory, $path);
                $contents = $zip->getFromIndex($entry['index']);
                if ($contents === false || ! hash_equals($entry['hash'], hash('sha256', $contents))) {
                    throw new RuntimeException('Runtime archive changed during extraction.');
                }
                self::write($directory.'/'.$path, $contents, 0644);
            }
        } finally {
            $zip->close();
        }
    }

    public static function backupWebsite(string $project, string $backup, bool $omitOwnedMaintenance = false): void
    {
        $zip = new ZipArchive;
        $archive = $backup.'/website.zip';
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Cannot create website backup.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS));
        $hashes = [];
        $links = [];
        try {
            foreach ($iterator as $file) {
                $path = substr($file->getPathname(), strlen($project) + 1);
                if (preg_match('~^(?:\.git/|node_modules/|storage/(?:kern-upgrader|kern-upgrades|kern-conversions)/)~', $path) || $path === 'storage/framework/kern-upgrade-tool.lock' || ($omitOwnedMaintenance && $path === 'storage/framework/down')) {
                    continue;
                }
                if ($file->isLink()) {
                    $links[$path] = readlink($file->getPathname());
                } elseif ($file->isFile()) {
                    if (! $zip->addFile($file->getPathname(), $path)) {
                        throw new RuntimeException('Cannot back up website file: '.$path);
                    }
                    $hashes[$path] = hash_file('sha256', $file->getPathname());
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Could not finish website backup. Check available disk space.');
            }
        } catch (Throwable $error) {
            @$zip->close();
            throw $error;
        }
        chmod($archive, 0600);
        $verification = new ZipArchive;
        if ($verification->open($archive, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('The website backup could not be verified.');
        }
        try {
            foreach ($hashes as $path => $hash) {
                $stream = $verification->getStream($path);
                if (! $stream) {
                    throw new RuntimeException('Missing backup entry: '.$path);
                }
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);
                fclose($stream);
                if (! hash_equals($hash, hash_final($context))) {
                    throw new RuntimeException('A file changed during backup. Retry after pausing writers: '.$path);
                }
            }
        } finally {
            $verification->close();
        }
        self::write($backup.'/files.json', json_encode(['files' => $hashes, 'symlinks' => $links, 'archive_sha256' => hash_file('sha256', $archive)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function snapshotHelper(string $runtime): string
    {
        $path = $this->workspace.'/snapshot.php';
        $source = <<<'PHP'
<?php
try {
    [$script, $project, $backup, $mode] = $argv;
    require $project.'/vendor/autoload.php';
    $app = require $project.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (! config('app.key') || ! \Illuminate\Support\Facades\Schema::hasTable('migrations')) {
        throw new \RuntimeException('Only an installed database with the existing APP_KEY can be upgraded.');
    }
    require_once __SNAPSHOT_CLASS__;
    $snapshot = new \Kern\Base\Supports\Upgrade\DatabaseSnapshot;
    $snapshot->check();
    if ($mode === 'inspect') {
        file_put_contents($backup.'/database-info.json', json_encode(['maintenance' => $app->isDownForMaintenance(), 'driver' => \Illuminate\Support\Facades\DB::connection()->getDriverName()], JSON_THROW_ON_ERROR));
        chmod($backup.'/database-info.json', 0600);
    } else {
        $snapshot->create($backup);
    }
} catch (\Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
PHP;
        self::write($path, str_replace('__SNAPSHOT_CLASS__', var_export($runtime.'/core/base/src/Supports/Upgrade/DatabaseSnapshot.php', true), $source));

        return $path;
    }

    private function alreadyUpgraded(string $version): bool
    {
        $path = $this->project.'/storage/app/kern/upgrade.json';
        if (! is_file($path)) {
            return false;
        }
        $state = self::json($path);
        foreach (self::json($this->project.'/composer.lock')['packages'] ?? [] as $package) {
            $runtime = $this->project.'/vendor/binhtq1/kern-core/core/base/resources/version.json';
            if ($package['name'] === 'binhtq1/kern-core' && ltrim($package['version'], 'v') === $version && ($state['version'] ?? null) === $version && isset($state['completed_at']) && is_file($runtime) && (self::json($runtime)['version'] ?? null) === $version) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, array{hash: string, index: int, archive: string}> $candidate
     * @param  array<string, array{hash: string, index: int, archive: string}>  $runtime
     */
    private function validatePackages(array $candidate, array $runtime, string $version): void
    {
        foreach (['composer.json', 'bin/migrate-to-core.php', 'core/base/src/Supports/Upgrade/DatabaseSnapshot.php', 'core/base/resources/version.json'] as $path) {
            if (! isset($runtime[$path])) {
                throw new RuntimeException('Incomplete runtime archive: '.$path);
            }
        }
        $core = json_decode(self::contents($runtime['composer.json']), true, flags: JSON_THROW_ON_ERROR);
        $starter = json_decode(self::contents($candidate['composer.json'] ?? throw new RuntimeException('Missing starter composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $metadata = json_decode(self::contents($runtime['core/base/resources/version.json']), true, flags: JSON_THROW_ON_ERROR);
        if (($core['name'] ?? null) !== 'binhtq1/kern-core' || ($starter['name'] ?? null) !== 'binhtq1/kern-cms' || ($starter['type'] ?? null) !== 'project' || ($starter['require']['binhtq1/kern-core'] ?? null) !== '^'.$version || ($metadata['version'] ?? null) !== $version) {
            throw new RuntimeException('Release package identity or version mismatch.');
        }
    }

    private function download(string $url, string $destination): void
    {
        echo 'Downloading '.basename($destination)."...\n";
        $context = stream_context_create(['http' => ['timeout' => 120, 'header' => "User-Agent: Kern-Upgrader\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $input = @fopen($url, 'rb', false, $context);
        $output = fopen($destination, 'xb');
        if (! $input || ! $output) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException('Could not download the verified release: '.$url);
        }
        try {
            $bytes = stream_copy_to_stream($input, $output, 268435457);
            if ($bytes === false || $bytes > 268435456 || ! feof($input)) {
                throw new RuntimeException('Release download was incomplete or too large.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    /** @param list<string> $command */
    private function command(array $command): void
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $this->project);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start: '.$command[0]);
        }
        fclose($pipes[0]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Command failed: '.implode(' ', array_slice($command, 0, 3)));
        }
    }

    private function status(string $status, ?string $version): void
    {
        self::write($this->backup.'/status.json', json_encode(['version' => $version, 'status' => $status, 'backup_complete' => $this->backupComplete, 'project' => $this->project, 'maintenance_started' => $this->maintenanceStarted, 'updated_at' => gmdate(DATE_ATOM)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private static function directory(string $path, int $mode = 0700): void
    {
        if (! is_dir($path) && ! mkdir($path, $mode, true) && ! is_dir($path)) {
            throw new RuntimeException('Cannot create directory: '.$path);
        }
    }

    private static function write(string $path, string $contents, int $mode = 0600): void
    {
        self::directory(dirname($path), $mode === 0644 ? 0755 : 0700);
        $temporary = $path.'.kern-'.bin2hex(random_bytes(6));
        try {
            if (file_put_contents($temporary, $contents) !== strlen($contents) || ! chmod($temporary, $mode) || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot write: '.$path);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Expected a JSON object: '.$path);
        }

        return $data;
    }

    private static function removeDirectory(string $path): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit((new Upgrader)->run($argv));
}
