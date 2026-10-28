<?php

namespace Kern\Theme\Providers;

use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\Supports\DashboardMenu as DashboardMenuSupport;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Setting\PanelSections\SettingCommonPanelSection;
use Kern\Theme\Commands\ThemeActivateCommand;
use Kern\Theme\Commands\ThemeAssetsPublishCommand;
use Kern\Theme\Commands\ThemeAssetsRemoveCommand;
use Kern\Theme\Commands\ThemeCreateCommand;
use Kern\Theme\Commands\ThemeOptionCheckMissingCommand;
use Kern\Theme\Commands\ThemeRemoveCommand;
use Kern\Theme\Commands\ThemeRenameCommand;
use Kern\Theme\Contracts\Theme as ThemeContract;
use Kern\Theme\Events\RenderingAdminBar;
use Kern\Theme\Theme;

class ThemeServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->singleton(ThemeContract::class, Theme::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/theme')
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadHelpers()
            ->loadRoutes()
            ->publishAssets();

        DashboardMenu::default()->beforeRetrieving(function (DashboardMenuSupport $menu): void {
            $config = $this->app['config'];

            $menu
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-appearance')
                        ->priority(2000)
                        ->name('packages/theme::theme.appearance')
                        ->icon('ti ti-brush')
                )
                ->when(
                    $config->get('packages.theme.general.display_theme_manager_in_admin_panel', true),
                    function (DashboardMenuSupport $menu): void {
                        $menu->registerItem(
                            DashboardMenuItem::make()
                                ->id('cms-core-theme')
                                ->priority(1)
                                ->parentId('cms-core-appearance')
                                ->name('packages/theme::theme.name')
                                ->icon('ti ti-palette')
                                ->route('theme.index')
                                ->permissions('theme.index')
                        );
                    }
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-theme-option')
                        ->priority(4)
                        ->parentId('cms-core-appearance')
                        ->name('packages/theme::theme.theme_options')
                        ->icon('ti ti-list-tree')
                        ->route('theme.options')
                        ->permissions('theme.options')
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-appearance-custom-css')
                        ->priority(5)
                        ->parentId('cms-core-appearance')
                        ->name('packages/theme::theme.custom_css')
                        ->icon('ti ti-file-type-css')
                        ->route('theme.custom-css')
                        ->permissions('theme.custom-css')
                )
                ->when(
                    $config->get('packages.theme.general.enable_custom_js'),
                    function (DashboardMenuSupport $menu): void {
                        $menu->registerItem(
                            DashboardMenuItem::make()
                                ->id('cms-core-appearance-custom-js')
                                ->priority(6)
                                ->parentId('cms-core-appearance')
                                ->name('packages/theme::theme.custom_js')
                                ->icon('ti ti-file-type-js')
                                ->route('theme.custom-js')
                                ->permissions('theme.custom-js')
                        );
                    }
                )
                ->when(
                    $config->get('packages.theme.general.enable_custom_html'),
                    function (DashboardMenuSupport $menu): void {
                        $menu->registerItem(
                            DashboardMenuItem::make()
                                ->id('cms-core-appearance-custom-html')
                                ->priority(6)
                                ->parentId('cms-core-appearance')
                                ->name('packages/theme::theme.custom_html')
                                ->icon('ti ti-file-type-html')
                                ->route('theme.custom-html')
                                ->permissions('theme.custom-html')
                        );
                    }
                )
                ->when(
                    $config->get('packages.theme.general.enable_robots_txt_editor'),
                    function (DashboardMenuSupport $menu): void {
                        $menu->registerItem(
                            DashboardMenuItem::make()
                                ->id('cms-core-appearance-robots-txt')
                                ->priority(6)
                                ->parentId('cms-core-appearance')
                                ->name('packages/theme::theme.robots_txt_editor')
                                ->icon('ti ti-file-type-txt')
                                ->route('theme.robots-txt')
                                ->permissions('theme.robots-txt')
                        );
                    }
                );
        });

        PanelSectionManager::default()->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SettingCommonPanelSection::class,
                fn () => PanelSectionItem::make('website_tracking')
                    ->setTitle(trans('packages/theme::theme.settings.website_tracking.title'))
                    ->withIcon('ti ti-world')
                    ->withDescription(trans('packages/theme::theme.settings.website_tracking.description'))
                    ->withPriority(140)
                    ->withRoute('settings.website-tracking'),
            );
        });

        $this->app['events']->listen(RenderingAdminBar::class, function (): void {
            admin_bar()
                ->registerLink(trans('packages/theme::theme.name'), route('theme.index'), 'appearance', 'theme.index')
                ->registerLink(
                    trans('packages/theme::theme.theme_options'),
                    route('theme.options'),
                    'appearance',
                    'theme.options'
                );
        });

        $this->app->booted(function (): void {
            $this->app->register(HookServiceProvider::class);
        });

        $this->app->register(ThemeManagementServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ThemeActivateCommand::class,
                ThemeCreateCommand::class,
                ThemeRemoveCommand::class,
                ThemeAssetsPublishCommand::class,
                ThemeOptionCheckMissingCommand::class,
                ThemeAssetsRemoveCommand::class,
                ThemeRenameCommand::class,
            ]);
        }
    }
}
