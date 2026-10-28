<?php

namespace Kern\Api\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Events\RouteMatched;
use Kern\Api\Commands\GenerateDocumentationCommand;
use Kern\Api\Facades\ApiHelper;
use Kern\Api\Http\Middleware\ForceJsonResponseMiddleware;
use Kern\Api\Models\PersonalAccessToken;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Setting\PanelSections\SettingCommonPanelSection;
use Laravel\Sanctum\Sanctum;

class ApiServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app['config']->set([
            'scribe.routes.0.match.prefixes' => ['api/*'],
            'scribe.routes.0.apply.headers' => [
                'Authorization' => 'Bearer {token}',
                'Api-Version' => 'v1',
            ],
        ]);

        if (class_exists('ApiHelper')) {
            AliasLoader::getInstance()->alias('ApiHelper', ApiHelper::class);
        }
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/api')
            ->loadRoutes()
            ->loadAndPublishConfigurations(['api', 'permissions'])
            ->loadAndPublishTranslations()
            ->loadMigrations()
            ->loadAndPublishViews();

        if (ApiHelper::enabled()) {
            $this->loadRoutes(['api']);
        }

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->app['events']->listen(RouteMatched::class, function () {
            if (ApiHelper::enabled()) {
                $this->app['router']->pushMiddlewareToGroup('api', ForceJsonResponseMiddleware::class);
            }
        });

        PanelSectionManager::beforeRendering(function () {
            PanelSectionManager::default()
                ->registerItem(
                    SettingCommonPanelSection::class,
                    fn () => PanelSectionItem::make('settings.common.api')
                        ->setTitle(trans('packages/api::api.settings'))
                        ->withDescription(trans('packages/api::api.settings_description'))
                        ->withIcon('ti ti-api')
                        ->withPriority(110)
                        ->withRoute('api.settings')
                );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocumentationCommand::class,
            ]);
        }
    }

    protected function getPath(?string $path = null): string
    {
        return __DIR__.'/../..'.($path ? '/'.ltrim($path, '/') : '');
    }
}
