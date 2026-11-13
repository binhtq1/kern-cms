<?php

namespace Kern\Analytics\Providers;

use Illuminate\Foundation\AliasLoader;
use Kern\Analytics\Abstracts\AnalyticsAbstract;
use Kern\Analytics\Analytics;
use Kern\Analytics\Exceptions\InvalidConfiguration;
use Kern\Analytics\Facades\Analytics as AnalyticsFacade;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Setting\PanelSections\SettingOthersPanelSection;

class AnalyticsServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(AnalyticsAbstract::class, function () {
            if (! ($credentials = setting('analytics_service_account_credentials'))) {
                throw InvalidConfiguration::credentialsIsNotValid();
            }

            if (! ($propertyId = setting('analytics_property_id')) || ! is_numeric($propertyId)) {
                throw InvalidConfiguration::invalidPropertyId();
            }

            return new Analytics($propertyId, $credentials);
        });

        AliasLoader::getInstance()->alias('Analytics', AnalyticsFacade::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/analytics')
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadMigrations()
            ->publishAssets();

        PanelSectionManager::default()->beforeRendering(function (): void {
            if (! config('plugins.analytics.general.enabled_dashboard_widgets', true)) {
                return;
            }

            PanelSectionManager::registerItem(
                SettingOthersPanelSection::class,
                fn () => PanelSectionItem::make('analytics')
                    ->setTitle(trans('plugins/analytics::analytics.settings.title'))
                    ->withIcon('ti ti-brand-google-analytics')
                    ->withDescription(trans('plugins/analytics::analytics.settings.description'))
                    ->withPriority(160)
                    ->withRoute('analytics.settings')
            );
        });

        $this->app->booted(function (): void {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
