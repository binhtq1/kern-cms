<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();
        $database = $connection->getDatabaseName();

        $usesTestingDatabase = match ($connection->getDriverName()) {
            'sqlite' => $database === ':memory:',
            'pgsql' => str_ends_with($database, '_test'),
            'mysql' => getenv('ADMIN_BACKUP_ACCEPTANCE_MYSQL') === '1'
                && $database === 'kern_acceptance_tests'
                && $connection->getConfig('host') === 'acceptance-mysql'
                && $connection->getConfig('username') === 'kern_acceptance',
            default => false,
        };

        if (! $app->environment('testing') || ! $usesTestingDatabase) {
            throw new RuntimeException('Refusing to run tests against this database. Use SQLite with DB_DATABASE=:memory: or a dedicated PostgreSQL database ending in _test.');
        }

        return $app;
    }

    protected function assertUsesTestingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $connection = config('database.default');
        $this->assertContains($connection, ['sqlite', 'pgsql']);

        if ($connection === 'sqlite') {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        } else {
            $this->assertStringEndsWith('_test', config('database.connections.pgsql.database'));
        }
    }
}
