<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
