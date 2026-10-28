<?php

namespace Kern\Widget\Providers;

use Illuminate\Contracts\Foundation\Application;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Theme\Events\RenderingAdminBar;
use Kern\Theme\Facades\AdminBar;
use Kern\Widget\Facades\WidgetGroup;
use Kern\Widget\Factories\WidgetFactory;
use Kern\Widget\Models\Widget;
use Kern\Widget\Repositories\Eloquent\WidgetRepository;
use Kern\Widget\Repositories\Interfaces\WidgetInterface;
use Kern\Widget\WidgetGroupCollection;
use Kern\Widget\Widgets\CoreSimpleMenu;
use Kern\Widget\Widgets\Text;

class WidgetServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(WidgetInterface::class, function () {
            return new WidgetRepository(new Widget);
        });

        $this->app->bind('kern.widget', function (Application $app) {
            return new WidgetFactory($app);
        });

        $this->app->singleton('kern.widget-group-collection', function (Application $app) {
            return new WidgetGroupCollection($app);
        });
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/widget')
            ->loadAndPublishConfigurations(['permissions'])
            ->loadHelpers()
            ->loadRoutes()
            ->loadMigrations()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets();

        $this->app->booted(function (): void {
            WidgetGroup::setGroup([
                'id' => 'primary_sidebar',
                'name' => trans('packages/widget::widget.primary_sidebar_name'),
                'description' => trans('packages/widget::widget.primary_sidebar_description'),
            ]);

            register_widget(CoreSimpleMenu::class);
            register_widget(Text::class);
        });

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-widget')
                        ->parentId('cms-core-appearance')
                        ->priority(3)
                        ->name('packages/widget::widget.name')
                        ->icon('ti ti-layout')
                        ->route('widgets.index')
                        ->permissions('widgets.index')
                );
        });

        $this->app['events']->listen(RenderingAdminBar::class, function (): void {
            AdminBar::registerLink(
                trans('packages/widget::widget.name'),
                route('widgets.index'),
                'appearance',
                'widgets.index'
            );
        });
    }
}
