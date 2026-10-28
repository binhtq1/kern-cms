<?php

namespace Kern\Installer\Supports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EnvironmentManager
{
    public function save(Request $request): string
    {
        $this->write([
            'APP_NAME' => $request->string('app_name')->toString(),
            'APP_URL' => $request->string('app_url')->toString(),
            'DB_CONNECTION' => $request->string('database_connection')->toString(),
            'DB_HOST' => $request->string('database_hostname')->toString(),
            'DB_PORT' => $request->string('database_port')->toString(),
            'DB_DATABASE' => $request->string('database_name')->toString(),
            'DB_USERNAME' => $request->string('database_username')->toString(),
            'DB_PASSWORD' => $request->string('database_password')->toString(),
        ]);

        return trans('packages/installer::installer.environment.success');
    }

    public function turnOffDebugMode(): void
    {
        $this->write(['APP_DEBUG' => 'false']);
    }

    protected function write(array $values): void
    {
        $path = app()->environmentFilePath();
        $content = File::get(File::exists($path) ? $path : base_path('.env.example'));

        foreach ($values as $key => $value) {
            $line = $key.'='.str_replace('$', '\\$', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $pattern = '/^\h*(?:#\h*)?'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn (): string => $line, $content);
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }

        File::replace($path, $content);
        File::delete(app()->getCachedConfigPath());
    }
}
