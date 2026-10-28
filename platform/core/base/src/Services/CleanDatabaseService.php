<?php

namespace Kern\Base\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Kern\Setting\Facades\Setting;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use Throwable;

class CleanDatabaseService
{
    public function getIgnoreTables(): array
    {
        return [
            'migrations',
            'pages',
            'users',
            'activations',
            'settings',
            'translations',
            'widgets',
            'menus',
            'menu_nodes',
        ];
    }

    public function execute(array $except = []): bool
    {
        $except = array_merge($except, $this->getIgnoreTables());

        try {
            $tables = DB::connection()->getSchemaBuilder()->getTableListing();
            $tables = array_values(array_filter($tables, fn (string $table): bool => ! in_array($table, $except, true) && ! in_array(str($table)->afterLast('.')->toString(), $except, true)));
        } catch (Throwable) {
            $tables = [];
        }

        if (empty($tables)) {
            return false;
        }

        Schema::withoutForeignKeyConstraints(function () use ($tables): void {
            foreach (Arr::except($tables, 'settings') as $table) {
                DB::table($table)->truncate();
            }
        });

        $tableNames = array_map(fn (string $table): string => str($table)->afterLast('.')->toString(), $tables);

        if (in_array('settings', $tableNames, true)) {
            Setting::forceDelete(except: [
                'theme',
                'activated_plugins',
                'media_random_hash',
            ]);
        }

        if (in_array('media_files', $tableNames, true)) {
            $storage = Storage::disk(config('core.media.media.disk', config('filesystems.default')));

            foreach ($storage->directories() as $directory) {
                if (! $storage->deleteDirectory($directory)) {
                    throw UnableToDeleteDirectory::atLocation($directory);
                }
            }

            foreach ($storage->files() as $file) {
                if (! $storage->delete($file)) {
                    throw UnableToDeleteFile::atLocation($file);
                }
            }
        }

        return true;
    }
}
