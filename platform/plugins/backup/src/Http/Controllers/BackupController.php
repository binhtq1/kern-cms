<?php

namespace Kern\Backup\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Kern\Backup\Http\Requests\BackupRequest;
use Kern\Backup\Supports\Backup;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseSystemController;
use Kern\Base\Supports\Helper;
use RuntimeException;

class BackupController extends BaseSystemController
{
    protected string $databaseDriver;

    public function __construct(protected Backup $backup)
    {
        $this->databaseDriver = DB::getConfig('driver');
    }

    public function getIndex()
    {
        $this->pageTitle(trans('plugins/backup::backup.menu_name'));

        $backupManager = $this->backup;

        $backups = $this->backup->getBackupList();

        $driver = $this->databaseDriver;

        $user = request()->user();
        $warnings = [];
        if (! function_exists('proc_open')) {
            $warnings[] = strip_tags(trans('plugins/backup::backup.proc_open_disabled_error'));
        }
        $warningKeys = match ($driver) {
            'mysql' => ['important_message1', 'important_message2', 'important_message3', 'important_message4'],
            'pgsql' => ['important_message_pgsql1', 'important_message_pgsql2'],
            default => [],
        };
        foreach ($warningKeys as $warningKey) {
            $warnings[] = strip_tags(trans('plugins/backup::backup.'.$warningKey));
        }

        return Inertia::render('System/Console', [
            'title' => trans('plugins/backup::backup.menu_name'),
            'description' => $driver !== 'mysql' ? trans('plugins/backup::backup.database_driver_not_supported') : null,
            'warnings' => $warnings,
            'columns' => [['key' => 'name', 'label' => __('Name')], ['key' => 'description', 'label' => __('Description')], ['key' => 'size', 'label' => __('Size')], ['key' => 'date', 'label' => __('Created at')]],
            'rows' => collect($backups)->map(function ($data, $key) use ($user, $driver) {
                $actions = [];
                if ($this->backup->isDatabaseBackupAvailable($key)) {
                    $actions[] = ['label' => __('Download database'), 'url' => route('backups.download.database', $key), 'download' => true];
                }
                $actions[] = ['label' => __('Download uploads'), 'url' => route('backups.download.uploads.folder', $key), 'download' => true];
                if ($driver === 'mysql' && $user->hasPermission('backups.restore')) {
                    $actions[] = ['label' => __('Restore'), 'url' => route('backups.restore', $key), 'method' => 'post', 'confirm' => __('This replaces the current database and uploaded files. Continue?'), 'destructive' => true];
                }
                if ($user->hasPermission('backups.destroy')) {
                    $actions[] = ['label' => __('Delete'), 'url' => route('backups.destroy', $key), 'method' => 'delete', 'confirm' => __('Delete this backup permanently?'), 'destructive' => true];
                }

                return ['id' => $key, 'values' => ['name' => $data['name'], 'description' => $data['description'] ?? '', 'date' => $data['date'] ?? '', 'size' => BaseHelper::humanFilesize(get_backup_size($key))], 'actions' => $actions];
            })->values()->all(),
            'actions' => $user->hasPermission('backups.create') ? [[
                'label' => __('Create backup'), 'url' => route('backups.create'), 'method' => 'post', 'disabled' => $driver !== 'mysql',
                'fields' => [['name' => 'name', 'label' => __('Name')], ['name' => 'description', 'label' => __('Description')]],
            ]] : [],
        ]);
    }

    public function store(BackupRequest $request)
    {
        if ($this->databaseDriver !== 'mysql') {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/backup::backup.database_driver_not_supported'));
        }

        try {
            BaseHelper::maximumExecutionTimeAndMemoryLimit();

            $data = $this->backup->createBackupFolder($request->input('name'), $request->input('description'));
            if (! $this->backup->backupDb() || ! $this->backup->backupFolder(config('filesystems.disks.public.root'))) {
                $this->backup->deleteFolderBackup($this->backup->getBackupPath($data['key']));

                throw new RuntimeException(trans('plugins/backup::backup.cannot_create_backup'));
            }

            do_action(BACKUP_ACTION_AFTER_BACKUP, BACKUP_MODULE_SCREEN_NAME, $request);

            return $this
                ->httpResponse()
                ->setData(['key' => $data['key'], 'name' => $data['data']['name'], 'description' => $data['data']['description'] ?? '', 'date' => $data['data']['date'] ?? ''])
                ->setMessage(trans('plugins/backup::backup.create_backup_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function destroy(string $folder)
    {
        $path = $this->resolveBackupFolder($folder);

        try {
            $this->backup->deleteFolderBackup($path);

            return $this
                ->httpResponse()
                ->setMessage(trans('plugins/backup::backup.delete_backup_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function getRestore(string $folder, Request $request)
    {
        $path = $this->resolveBackupFolder($folder);
        if ($this->databaseDriver !== 'mysql') {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/backup::backup.database_driver_not_supported'));
        }

        $stagingPath = null;

        try {
            foreach (BaseHelper::scanFolder($path) as $file) {
                if (preg_match('/^storage-.*\\.zip$/', basename($file))) {
                    $stagingPath = $path.'/.uploads-restore-'.Str::uuid();
                    if (! $this->backup->extractFileTo($path.DIRECTORY_SEPARATOR.$file, $stagingPath)) {
                        throw new RuntimeException(trans('plugins/backup::backup.cannot_restore_uploads'));
                    }
                    break;
                }
            }

            $hasSQL = false;

            foreach (BaseHelper::scanFolder($path) as $file) {
                if (preg_match('/^database-.*\\.zip$/', basename($file))) {
                    $hasSQL = $this->backup->restoreDatabase($path.DIRECTORY_SEPARATOR.$file, $path);
                    break;
                }
            }

            if (! $hasSQL) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage(trans('plugins/backup::backup.cannot_restore_database'));
            }

            if ($stagingPath !== null) {
                $pathTo = config('filesystems.disks.public.root');
                $this->backup->cleanDirectory($pathTo);
                if (! File::copyDirectory($stagingPath, $pathTo)) {
                    throw new RuntimeException(trans('plugins/backup::backup.cannot_restore_uploads'));
                }
            }

            setting()->forceSet('media_random_hash', md5((string) time()))->save();

            Helper::clearCache();

            do_action(BACKUP_ACTION_AFTER_RESTORE, BACKUP_MODULE_SCREEN_NAME, $request);

            return $this
                ->httpResponse()
                ->setMessage(trans('plugins/backup::backup.restore_backup_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        } finally {
            if ($stagingPath !== null) {
                File::deleteDirectory($stagingPath);
            }
        }
    }

    public function getDownloadDatabase(string $folder)
    {
        $path = $this->resolveBackupFolder($folder);

        foreach (BaseHelper::scanFolder($path) as $file) {
            if (preg_match('/^database-.*\\.zip$/', basename($file))) {
                return response()->download($path.DIRECTORY_SEPARATOR.$file);
            }
        }

        return $this
            ->httpResponse()
            ->setError()
            ->setMessage(trans('plugins/backup::backup.database_backup_not_existed'));
    }

    public function getDownloadUploadFolder(string $folder)
    {
        $path = $this->resolveBackupFolder($folder);

        foreach (BaseHelper::scanFolder($path) as $file) {
            if (preg_match('/^storage-.*\\.zip$/', basename($file))) {
                return response()->download($path.DIRECTORY_SEPARATOR.$file);
            }
        }

        return $this
            ->httpResponse()
            ->setError()
            ->setMessage(trans('plugins/backup::backup.uploads_folder_backup_not_existed'));
    }

    protected function resolveBackupFolder(string $folder): string
    {
        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/D', $folder)
            && array_key_exists($folder, $this->backup->getBackupList()),
            404,
        );

        return $this->backup->getBackupPath($folder);
    }
}
