<?php

namespace Kern\PluginManagement\Commands;

use Illuminate\Console\Command;
use Kern\Base\Facades\BaseHelper;
use Kern\PluginManagement\Services\PluginService;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:plugin:deactivate:all', 'Deactivate all plugins in /plugins directory')]
class PluginDeactivateAllCommand extends Command
{
    public function handle(PluginService $pluginService): int
    {
        foreach (BaseHelper::scanFolder(plugin_path()) as $plugin) {
            $pluginService->deactivate($plugin);
        }

        $this->components->info('Deactivated successfully!');

        return self::SUCCESS;
    }
}
