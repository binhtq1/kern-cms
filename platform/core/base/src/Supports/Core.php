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
        return json_decode(file_get_contents(core_path('base/resources/version.json')), true, flags: JSON_THROW_ON_ERROR)['version'];
    }

    public function compatibilityVersion(): string
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
