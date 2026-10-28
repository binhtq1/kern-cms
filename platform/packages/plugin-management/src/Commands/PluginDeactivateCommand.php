<?php

namespace Kern\PluginManagement\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use Kern\PluginManagement\Commands\Concern\HasPluginNameValidation;
use Kern\PluginManagement\Services\PluginService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('cms:plugin:deactivate', 'Deactivate a plugin in /plugins directory')]
class PluginDeactivateCommand extends Command implements PromptsForMissingInput
{
    use HasPluginNameValidation;

    public function handle(PluginService $pluginService): int
    {
        $name = $this->argument('name');

        $name = rtrim($name, '/');

        $this->validatePluginName($name);

        $plugin = Str::afterLast(strtolower($name), '/');

        $result = $pluginService->deactivate($plugin);

        if ($result['error']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $this->components->info($result['message']);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The plugin that you want to deactivate');
    }
}
