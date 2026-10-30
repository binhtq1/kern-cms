<?php

namespace Kern\Backup\Supports\PgSql;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class Restore
{
    public function restore(string $file): bool
    {
        $config = DB::getConfig();
        $directory = rtrim((string) config('plugins.backup.general.pgsql.execute_path'), '/');
        $binary = $directory ? $directory.'/pg_restore' : 'pg_restore';

        (new Process([
            $binary, '--username='.$config['username'], '--host='.$config['host'],
            '--port='.$config['port'], '--dbname='.$config['database'],
            '--clean', '--if-exists', '--no-owner', '--no-acl', '--exit-on-error', $file,
        ], env: ['PGPASSWORD' => $config['password'] ?? '']))->mustRun();

        return true;
    }
}
