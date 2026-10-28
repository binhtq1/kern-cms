<?php

namespace Kern\Backup;

use Illuminate\Support\Facades\File;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        $backupPath = storage_path('app/backup');

        if (File::isDirectory($backupPath)) {
            File::deleteDirectory($backupPath);
        }
    }
}
