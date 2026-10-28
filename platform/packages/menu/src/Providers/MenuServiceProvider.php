<?php

namespace Kern\Menu\Providers;

use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Menu\Models\MenuLocation;
use Kern\Menu\Models\MenuNode;
use Kern\Menu\Repositories\Eloquent\MenuLocationRepository;
use Kern\Menu\Repositories\Eloquent\MenuNodeRepository;
use Kern\Menu\Repositories\Eloquent\MenuRepository;
use Kern\Menu\Repositories\Interfaces\MenuInterface;
use Kern\Menu\Repositories\Interfaces\MenuLocationInterface;
use Kern\Menu\Repositories\Interfaces\MenuNodeInterface;
use Kern\Theme\Events\RenderingAdminBar;
use Kern\Theme\Facades\AdminBar;

class MenuServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(MenuInterface::class, function () {
            return new MenuRepository(new MenuModel);
        });

        $this->app->bind(MenuNodeInterface::class, function () {
            return new MenuNodeRepository(new MenuNode);
        });

        $this->app->bind(MenuLocationInterface::class, function () {
            return new MenuLocationRepository(new MenuLocation);
        });
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/menu')
            ->loadAndPublishConfigurations(['permissions', 'general'])
            ->loadHelpers()
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadMigrations()
            ->publishAssets();

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-menu')
                        ->parentId('cms-core-appearance')
                        ->priority(2)
                        ->name('packages/menu::menu.name')
                        ->icon('ti ti-tournament')
                        ->route('menus.index')
                        ->permissions('menus.index')
                );
        });

        $this->app['events']->listen(RenderingAdminBar::class, function (): void {
            AdminBar::registerLink(
                trans('packages/menu::menu.name'),
                route('menus.index'),
                'appearance',
                'menus.index'
            );
        });

        $this->app->register(EventServiceProvider::class);
        $this->app->register(CommandServiceProvider::class);
    }
}
