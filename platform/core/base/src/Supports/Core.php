<?php

namespace Kern\Base\Supports;

use Illuminate\Support\Facades\Artisan;

final class Core
{
    public static function make(): self
    {
        return app(self::class);
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function minimumPhpVersion(): string
    {
        return '8.4.0';
    }

    public function runMigrationFiles(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }
}
