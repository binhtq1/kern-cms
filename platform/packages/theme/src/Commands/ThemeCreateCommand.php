<?php

namespace Kern\Theme\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand('cms:theme:create', 'Create a new theme with Vite assets')]
class ThemeCreateCommand extends Command
{
    protected $signature = 'cms:theme:create {name : Theme name in kebab-case}';

    public function handle(Filesystem $files): int
    {
        $name = strtolower((string) $this->argument('name'));

        if (strlen($name) > 64 || ! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $name)) {
            $this->components->error('Use a theme name of up to 64 letters, numbers, and single hyphens, starting with a letter.');

            return self::FAILURE;
        }

        $path = theme_path($name);

        if ($files->exists($path) || is_link($path)) {
            $this->components->error("Theme [$name] already exists.");

            return self::FAILURE;
        }

        $created = false;

        try {
            if (! $files->makeDirectory($path)) {
                throw new RuntimeException('Unable to create the theme directory.');
            }

            $created = true;

            foreach (['functions', 'lang', 'public', 'src', 'widgets'] as $directory) {
                $files->ensureDirectoryExists($path.'/'.$directory);
            }

            foreach ($files->allFiles(dirname(__DIR__, 2).'/stubs/theme') as $stub) {
                $relativePath = $stub->getRelativePathname();

                if (! str_ends_with($relativePath, '.stub')) {
                    continue;
                }

                $destination = $path.'/'.substr($relativePath, 0, -5);
                $files->ensureDirectoryExists(dirname($destination));

                if (! $files->copy($stub->getPathname(), $destination)) {
                    throw new RuntimeException("Unable to create [$relativePath].");
                }
            }

            foreach ([
                'config.php', 'routes/web.php', 'layouts/default.blade.php',
                'partials/header.blade.php', 'partials/footer.blade.php', 'partials/posts.blade.php',
                'views/index.blade.php', 'views/page.blade.php', 'views/post.blade.php',
                'views/category.blade.php', 'views/tag.blade.php', 'views/search.blade.php',
                'assets/sass/style.scss', 'assets/js/app.js',
            ] as $requiredFile) {
                if (! $files->isFile($path.'/'.$requiredFile)) {
                    throw new RuntimeException("Theme template [$requiredFile] is missing.");
                }
            }

            $metadata = [
                'id' => 'kern/'.$name,
                'name' => Str::headline($name),
                'namespace' => 'Theme\\'.Str::studly($name).'\\',
                'description' => '',
                'author' => '',
                'version' => '1.0.0',
                'url' => '',
                'vite' => ['entrypoints' => ['assets/sass/style.scss', 'assets/js/app.js']],
                'required_plugins' => [],
            ];

            if ($files->put($path.'/theme.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL) === false) {
                throw new RuntimeException('Unable to create theme.json.');
            }
        } catch (Throwable $exception) {
            if ($created) {
                $files->deleteDirectory($path);
            }

            $this->components->error('Unable to create theme: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Theme [$name] created at [$path].");
        $this->line("Build it with: npm run build:theme -- $name");
        $this->line("Activate it with: php artisan cms:theme:activate $name --no-interaction");

        return self::SUCCESS;
    }
}
