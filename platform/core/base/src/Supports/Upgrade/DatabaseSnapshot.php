<?php

namespace Kern\Base\Supports\Upgrade;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseSnapshot
{
    public function check(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $path = $connection->getConfig('database');
            if ($path === ':memory:' || ! is_file($path)) {
                throw new RuntimeException('An on-disk SQLite database is required for an upgrade snapshot.');
            }
        } else {
            $binary = match ($driver) {
                'pgsql' => 'pg_dump',
                'mysql' => 'mysqldump',
                default => throw new RuntimeException('Unsupported database driver for upgrade snapshots.'),
            };
            if (! (new ExecutableFinder)->find($binary)) {
                throw new RuntimeException('Install '.$binary.' before upgrading this database.');
            }
        }
    }

    public function create(string $directory): string
    {
        $connection = DB::connection();
        $config = $connection->getConfig();
        if ($connection->getDriverName() === 'sqlite') {
            if ($config['database'] === ':memory:') {
                throw new RuntimeException('An on-disk SQLite database is required for an upgrade snapshot.');
            }
            $path = $directory.'/database.sqlite';
            $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($path));
        } elseif ($connection->getDriverName() === 'pgsql') {
            $path = $directory.'/database.pgsql';
            $process = new Process(['pg_dump', '--no-password', '--format=custom', '--host='.$config['host'], '--port='.($config['port'] ?? 5432), '--username='.$config['username'], '--file='.$path, '--dbname='.$config['database']], env: ['PGPASSWORD' => $config['password'] ?? '', 'PGSSLMODE' => $config['sslmode'] ?? 'prefer']);
            $this->run($process);
        } elseif ($connection->getDriverName() === 'mysql') {
            $path = $directory.'/database.sql';
            $credentials = $directory.'/mysql.cnf';
            $options = "[client]\n";
            foreach (['host', 'port', 'username' => 'user', 'password'] as $key => $option) {
                $key = is_int($key) ? $option : $key;
                $options .= $option.'="'.addcslashes((string) ($config[$key] ?? ''), "\\\"\n\r")."\"\n";
            }
            file_put_contents($credentials, $options);
            chmod($credentials, 0600);
            try {
                $this->run(new Process(['mysqldump', '--defaults-extra-file='.$credentials, '--single-transaction', '--result-file='.$path, '--', $config['database']]));
            } finally {
                unlink($credentials);
            }
        } else {
            throw new RuntimeException('Unsupported database driver for upgrade snapshots.');
        }
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('The database snapshot is empty. Upgrade stopped.');
        }
        chmod($path, 0600);

        return $path;
    }

    private function run(Process $process): void
    {
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Database snapshot failed. Check that the matching database client is installed and can connect.');
        }
    }
}
