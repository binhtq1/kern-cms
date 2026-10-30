<?php

namespace Kern\Backup\Supports\PgSql;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class Backup
{
    public function backup(string $path): string
    {
        $config = DB::getConfig();
        $directory = rtrim((string) config('plugins.backup.general.pgsql.execute_path'), '/');
        $binary = $directory ? $directory.'/pg_dump' : 'pg_dump';
        $file = $path.'.dump';

        (new Process([
            $binary, '--username='.$config['username'], '--host='.$config['host'],
            '--port='.$config['port'], '--dbname='.$config['database'],
            '--format=custom', '--file='.$file,
        ], env: ['PGPASSWORD' => $config['password'] ?? '']))->mustRun();

        return $file;
    }
}
