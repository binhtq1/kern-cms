<?php

namespace Kern\Setting\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Events\RouteMatched;
use Kern\Base\Events\PanelSectionsRendering;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\PanelSections\System\SystemPanelSection;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Setting\Commands\CronJobTestCommand;
use Kern\Setting\Facades\Setting;
use Kern\Setting\Listeners\PushDashboardMenuToOtherSectionPanel;
use Kern\Setting\Models\Setting as SettingModel;
use Kern\Setting\PanelSections\SettingCommonPanelSection;
use Kern\Setting\PanelSections\SettingOthersPanelSection;
use Kern\Setting\Repositories\Eloquent\SettingRepository;
use Kern\Setting\Repositories\Interfaces\SettingInterface;
use Kern\Setting\Supports\DatabaseSettingStore;
use Kern\Setting\Supports\SettingStore;

class SettingServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    protected bool $defer = true;

    public function register(): void
    {
        $this
            ->setNamespace('core/setting')
            ->loadAndPublishConfigurations(['general']);

        $this->app->singleton(SettingStore::class, function () {
            return new DatabaseSettingStore;
        });

        $this->app->bind(SettingInterface::class, function () {
            return new SettingRepository(new SettingModel);
        });

        if (! class_exists('Setting')) {
            AliasLoader::getInstance()->alias('Setting', Setting::class);
        }

        $this->loadHelpers();
    }

    public function boot(): void
    {
        $this
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAnonymousComponents()
            ->loadAndPublishTranslations()
            ->loadAndPublishConfigurations(['permissions', 'email'])
            ->loadMigrations()
            ->publishAssets();

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-settings')
                        ->priority(9999)
                        ->name('core/setting::setting.title')
                        ->icon('ti ti-settings')
                        ->route('settings.index')
                        ->permission('settings.index')
                );
        });

        $events = $this->app['events'];

        $events->listen(RouteMatched::class, function (): void {
            EmailHandler::addTemplateSettings('base', config('core.setting.email', []), 'core');
        });

        PanelSectionManager::default()
            ->beforeRendering(function (): void {
                PanelSectionManager::setGroupName(trans('core/setting::setting.title'))
                    ->register([
                        SettingCommonPanelSection::class,
                        SettingOthersPanelSection::class,
                    ]);
            });

        PanelSectionManager::group('system')->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SystemPanelSection::class,
                fn () => PanelSectionItem::make('cronjob')
                    ->setTitle(trans('core/setting::setting.cronjob.name'))
                    ->withIcon('ti ti-calendar-event')
                    ->withDescription(trans('core/setting::setting.cronjob.description'))
                    ->withPriority(50)
                    ->withRoute('system.cronjob')
            );
        });

        $events->listen(PanelSectionsRendering::class, PushDashboardMenuToOtherSectionPanel::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CronJobTestCommand::class,
            ]);

            $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
                rescue(function () use ($schedule): void {
                    $schedule
                        ->command(CronJobTestCommand::class)
                        ->everyMinute();
                });
            });
        }
    }

    public function provides(): array
    {
        return [
            SettingStore::class,
        ];
    }
}
