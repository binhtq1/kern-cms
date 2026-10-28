<?php

namespace Kern\PluginManagement\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kern\PluginManagement\Commands\Concern\HasPluginNameValidation;
use Kern\PluginManagement\Services\PluginService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cms:plugin:assets:publish', 'Publish assets for a plugin')]
class PluginAssetsPublishCommand extends Command
{
    use HasPluginNameValidation;

    public function handle(PluginService $pluginService): int
    {
        $this->validatePluginName($this->argument('name'));

        $plugin = Str::afterLast(strtolower($this->argument('name')), '/');
        $result = $pluginService->publishAssets($plugin);

        if ($result['error']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $this->components->info($result['message']);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The plugin that you want to publish assets');
    }
}
