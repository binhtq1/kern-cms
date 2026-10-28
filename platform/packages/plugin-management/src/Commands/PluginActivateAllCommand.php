<?php

namespace Kern\PluginManagement\Commands;

use Illuminate\Console\Command;
use Kern\PluginManagement\Services\PluginService;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:plugin:activate:all', 'Activate bundled plugins in dependency order')]
class PluginActivateAllCommand extends Command
{
    public function handle(PluginService $pluginService): int
    {
        $pending = array_diff(get_installed_plugins(), get_active_plugins());
        while ($pending) {
            $progress = false;
            foreach ($pending as $key => $plugin) {
                if ($pluginService->getDependencies($plugin)) {
                    continue;
                }
                $result = $pluginService->activate($plugin);
                if ($result['error']) {
                    $this->components->error($plugin.': '.$result['message']);

                    return self::FAILURE;
                }
                unset($pending[$key]);
                $progress = true;
            }
            if (! $progress) {
                $this->components->error('Missing or circular dependencies: '.implode(', ', $pending));

                return self::FAILURE;
            }
        }
        $this->components->info('All bundled plugins are active.');

        return self::SUCCESS;
    }
}
