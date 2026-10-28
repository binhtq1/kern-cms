<?php

namespace Kern\Base\Commands;

use Illuminate\Console\Command;
use Kern\Base\Facades\BaseHelper;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

#[AsCommand('cms:install', 'Install Kern CMS without a product key.')]
class InstallCommand extends Command
{
    protected $signature = 'cms:install {--force : Run without confirmation}';

    public function handle(): int
    {
        if (! $this->option('force') && ! confirm('Install Kern CMS?', default: false)) {
            return self::SUCCESS;
        }

        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->input->isInteractive() && confirm('Create an administrator?', default: true)) {
            if ($this->call('cms:user:create') !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if ($this->call('cms:publish:assets') !== self::SUCCESS) {
            return self::FAILURE;
        }

        BaseHelper::saveFileData(storage_path('installed'), now()->toDateTimeString());
        $this->components->info('Kern CMS installed. Create an administrator with php artisan cms:user:create.');

        return self::SUCCESS;
    }
}
