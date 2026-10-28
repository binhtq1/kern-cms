<?php

namespace Kern\Base\Supports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class Database
{
    public static function restoreFromPath(string $pathToSqlFile, ?string $connection = null): void
    {
        if (! File::exists($pathToSqlFile) || File::size($pathToSqlFile) < 1024) {
            throw new RuntimeException('Database backup is missing or invalid.');
        }

        try {
            DB::purge($connection);
            DB::connection()->setDatabaseName(DB::getDatabaseName());
            DB::getSchemaBuilder()->dropAllTables();
            DB::unprepared(file_get_contents($pathToSqlFile));
        } catch (Throwable $exception) {
            $config = DB::getConfig();

            $command = 'mysql --user="%s" --password="%s" --host="%s" --port="%s" "%s" < "%s"';

            $sql = sprintf(
                $command,
                $config['username'],
                $config['password'],
                $config['host'],
                $config['port'],
                $config['database'],
                $pathToSqlFile
            );

            try {
                Process::fromShellCommandline($sql)->mustRun();
            } catch (Throwable) {
                if (function_exists('system')) {
                    system($sql, $exitCode);
                    if ($exitCode !== 0) {
                        throw new RuntimeException($exception->getMessage(), previous: $exception);
                    }
                } else {
                    throw new RuntimeException($exception->getMessage(), previous: $exception);
                }
            }
        }
    }
}
