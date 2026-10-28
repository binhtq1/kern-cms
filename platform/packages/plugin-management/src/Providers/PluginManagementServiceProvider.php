<?php

namespace Kern\PluginManagement\Providers;

use Composer\Autoload\ClassLoader;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\PluginManagement\PluginManifest;

class PluginManagementServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('packages/plugin-management')
            ->loadAndPublishConfigurations(['permissions', 'general'])
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->loadHelpers()
            ->publishAssets();

        $manifest = (new PluginManifest)->getManifest();

        $loader = new ClassLoader;

        foreach ($manifest['namespaces'] as $key => $namespace) {
            $loader->setPsr4($namespace, plugin_path($key.'/src'));
        }

        $loader->register();

        foreach ($manifest['providers'] as $provider) {
            if (! class_exists($provider)) {
                continue;
            }

            $this->app->register($provider);
        }

        DashboardMenu::default()->beforeRetrieving(function (): void {
            if (config('packages.plugin-management.general.enable_plugin_manager', true)) {
                DashboardMenu::registerItem(DashboardMenuItem::make()
                    ->id('cms-core-plugins')->priority(3000)
                    ->name('packages/plugin-management::plugin.plugins')
                    ->icon('ti ti-plug')->route('plugins.index'));
            }
        });

        $this->app->register(CommandServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(HookServiceProvider::class);
    }
}
