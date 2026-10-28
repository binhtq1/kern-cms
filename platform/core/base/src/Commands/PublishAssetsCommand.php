<?php

namespace Kern\Base\Commands;

use Illuminate\Console\Command;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Supports\Upgrade\NativeFrontendCompatibility;
use Kern\Base\Supports\Upgrade\RuntimeAssets;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:publish:assets', 'Publish assets (CSS, JS, Images...)')]
class PublishAssetsCommand extends Command
{
    public function handle(RuntimeAssets $assets, NativeFrontendCompatibility $compatibility): int
    {
        try {
            $compatibility->assertSupported();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Publishing core and package assets...');
        if ($this->call('vendor:publish', ['--tag' => 'cms-public', '--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (defined('THEME_MODULE_SCREEN_NAME') && BaseHelper::scanFolder(theme_path())) {
            $this->components->info('Publishing theme assets...');
            if ($this->call('cms:theme:assets:publish') !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (is_dir(core_path('../public/build/admin'))) {
            $assets->record($assets->plan());
        }

        $this->components->info('Published assets successfully!');

        return self::SUCCESS;
    }
}
