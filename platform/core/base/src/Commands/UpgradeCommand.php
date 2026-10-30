<?php

namespace Kern\Base\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Kern\Base\Supports\Core;
use Kern\Base\Supports\Upgrade\DatabaseSnapshot;
use Kern\Base\Supports\Upgrade\RuntimeAssets;
use RuntimeException;
use Throwable;

class UpgradeCommand extends Command
{
    protected $signature = 'cms:upgrade {--check : Check prerequisites without upgrading}';

    protected $description = 'Back up the database and runtime assets, then upgrade the CMS';

    public function handle(Filesystem $files, RuntimeAssets $assets, DatabaseSnapshot $snapshot): int
    {
        $lockPath = storage_path('framework/kern-upgrade.lock');
        $files->ensureDirectoryExists(dirname($lockPath));
        $lock = fopen($lockPath, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->components->error('Another CMS upgrade is running.');

            return self::FAILURE;
        }
        $directory = null;
        $maintenanceFlag = storage_path('framework/kern-upgrade-maintenance');
        $maintenanceStarted = is_file($maintenanceFlag);
        try {
            if (! is_file(storage_path('installed')) || ! Schema::hasTable('migrations') || ! config('app.key')) {
                throw new RuntimeException('Only an installed website with an existing APP_KEY can be upgraded.');
            }
            $statePath = storage_path('app/kern/upgrade.json');
            $state = is_file($statePath) ? json_decode($files->get($statePath), true, flags: JSON_THROW_ON_ERROR) : [];
            $version = Core::make()->version();
            if (version_compare($state['version'] ?? '0.1.0', $version, '>')) {
                throw new RuntimeException('Downgrading an installed CMS is not supported. Restore the matching code and database snapshot.');
            }
            foreach (get_active_plugins() as $plugin) {
                $metadataPath = plugin_path($plugin.'/plugin.json');
                if (! is_file($metadataPath)) {
                    throw new RuntimeException('Missing active plugin: '.$plugin);
                }
                $metadata = json_decode($files->get($metadataPath), true, flags: JSON_THROW_ON_ERROR);
                if (version_compare(Core::make()->compatibilityVersion(), $metadata['minimum_core_version'] ?? '0.0.0', '<')) {
                    throw new RuntimeException('Incompatible active plugin: '.$plugin);
                }
            }
            $snapshot->check();
            $plan = $assets->plan();
            if ($this->option('check')) {
                $this->components->info('Upgrade prerequisites passed for Kern CMS '.$version.'.');

                return self::SUCCESS;
            }
            if (! app()->isDownForMaintenance()) {
                if ($this->call('down', ['--retry' => 60]) !== self::SUCCESS) {
                    throw new RuntimeException('Could not enable maintenance mode.');
                }
                $maintenanceStarted = true;
                $files->put($maintenanceFlag, 'owned by cms:upgrade');
            }
            $directory = storage_path('kern-upgrades/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(6)));
            $files->ensureDirectoryExists($directory, 0700);
            $files->put($directory.'/.gitignore', "*\n");
            foreach (['composer.json', 'composer.lock'] as $file) {
                if (is_file(base_path($file))) {
                    $files->copy(base_path($file), $directory.'/'.basename($file));
                    chmod($directory.'/'.basename($file), 0600);
                }
            }
            $environment = $this->laravel->environmentFilePath();
            if (is_file($environment)) {
                $files->copy($environment, $directory.'/.env');
                chmod($directory.'/.env', 0600);
            }
            $snapshot->create($directory);
            $assets->backup($plan, $directory);
            $this->components->info('Runtime asset backup: '.$directory.'/assets');
            $files->put($directory.'/status.json', json_encode(['target' => $version, 'status' => 'started'], JSON_THROW_ON_ERROR));
            $files->ensureDirectoryExists(dirname($statePath), 0700);
            $files->replace($statePath, json_encode(['version' => $version, 'status' => 'in_progress', 'snapshot' => $directory], JSON_THROW_ON_ERROR), 0600);
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                throw new RuntimeException('A migration failed. Fix the cause and rerun cms:upgrade.');
            }
            $assets->publish($plan);
            foreach (['config:clear', 'route:clear', 'view:clear', 'queue:restart'] as $command) {
                if ($this->call($command) !== self::SUCCESS) {
                    throw new RuntimeException('Cache refresh failed: '.$command);
                }
            }
            $files->ensureDirectoryExists(dirname($statePath), 0700);
            $files->replace($statePath, json_encode(['version' => $version, 'completed_at' => gmdate(DATE_ATOM), 'snapshot' => $directory], JSON_THROW_ON_ERROR), 0600);
            $files->put($directory.'/status.json', json_encode(['target' => $version, 'status' => 'completed'], JSON_THROW_ON_ERROR));
            if ($maintenanceStarted) {
                if ($this->call('up') !== self::SUCCESS) {
                    throw new RuntimeException('Could not leave maintenance mode.');
                }
                $files->delete($maintenanceFlag);
            }
            $this->components->info('Kern CMS '.$version.' upgraded. Database snapshot: '.$directory);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($directory) {
                $files->put($directory.'/status.json', json_encode(['status' => 'failed'], JSON_THROW_ON_ERROR));
                $this->components->info('Upgrade backup retained at: '.$directory);
            }
            $this->components->error($exception->getMessage());
            if ($maintenanceStarted) {
                $this->components->warn('Maintenance mode remains enabled. Fix the failure and rerun cms:upgrade before bringing the website up.');
            }

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
