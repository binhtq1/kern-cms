<?php

namespace Kern\DataSynchronize\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Facades\PanelSectionManager as PanelSectionManagerFacade;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\DataSynchronize\Commands\ClearChunksCommand;
use Kern\DataSynchronize\Commands\ExportCommand;
use Kern\DataSynchronize\Commands\ExportControllerMakeCommand;
use Kern\DataSynchronize\Commands\ExporterMakeCommand;
use Kern\DataSynchronize\Commands\ImportCommand;
use Kern\DataSynchronize\Commands\ImportControllerMakeCommand;
use Kern\DataSynchronize\Commands\ImporterMakeCommand;
use Kern\DataSynchronize\PanelSections\ExportPanelSection;
use Kern\DataSynchronize\PanelSections\ImportPanelSection;

class DataSynchronizeServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('packages/data-synchronize')
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->loadAndPublishConfigurations(['data-synchronize'])
            ->loadAndPublishViews()
            ->publishAssets()
            ->registerPanelSection()
            ->registerDashboardMenu();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImporterMakeCommand::class,
                ExporterMakeCommand::class,
                ImportControllerMakeCommand::class,
                ExportControllerMakeCommand::class,
                ClearChunksCommand::class,
                ExportCommand::class,
                ImportCommand::class,
            ]);

            $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule
                    ->command(ClearChunksCommand::class)
                    ->dailyAt('00:00');
            });
        }
    }

    protected function getPath(?string $path = null): string
    {
        return __DIR__.'/../..'.($path ? '/'.ltrim($path, '/') : '');
    }

    protected function registerPanelSection(): self
    {
        PanelSectionManagerFacade::group('data-synchronize')->beforeRendering(function () {
            PanelSectionManagerFacade::default()
                ->register(ExportPanelSection::class)
                ->register(ImportPanelSection::class);
        });

        return $this;
    }

    protected function registerDashboardMenu(): self
    {
        DashboardMenu::default()->beforeRetrieving(function () {
            DashboardMenu::make()
                ->registerItem([
                    'id' => 'cms-packages-data-synchronize',
                    'parent_id' => 'cms-core-tools',
                    'priority' => 9000,
                    'name' => 'packages/data-synchronize::data-synchronize.tools.export_import_data',
                    'icon' => 'ti ti-package-import',
                    'route' => 'tools.data-synchronize',
                ]);
        });

        return $this;
    }
}
