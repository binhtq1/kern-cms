<?php

namespace Kern\Base\Providers;

use DateTimeZone;
use Illuminate\Config\Repository;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\ResourceRegistrar;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Kern\Base\Contracts\GlobalSearchableManager as GlobalSearchableManagerContract;
use Kern\Base\Contracts\PanelSections\Manager;
use Kern\Base\Facades\AdminAppearance;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\Breadcrumb as BreadcrumbFacade;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Facades\PageTitle;
use Kern\Base\Facades\PanelSectionManager as PanelSectionManagerFacade;
use Kern\Base\GlobalSearch\GlobalSearchableManager;
use Kern\Base\Models\BaseModel;
use Kern\Base\PanelSections\Manager as PanelSectionManager;
use Kern\Base\PanelSections\System\SystemPanelSection;
use Kern\Base\Repositories\Eloquent\MetaBoxRepository;
use Kern\Base\Repositories\Interfaces\MetaBoxInterface;
use Kern\Base\Supports\Action;
use Kern\Base\Supports\Breadcrumb;
use Kern\Base\Supports\CustomResourceRegistrar;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\Database\Blueprint;
use Kern\Base\Supports\Filter;
use Kern\Base\Supports\GoogleFonts;
use Kern\Base\Supports\Helper;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Base\Widgets\AdminWidget;
use Kern\Base\Widgets\Contracts\AdminWidget as AdminWidgetContract;
use Kern\Setting\Providers\SettingServiceProvider;
use Kern\Setting\Supports\SettingStore;

class BaseServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this
            ->setNamespace('core/base')
            ->loadAndPublishConfigurations(['general', 'admin'])
            ->loadHelpers();

        $this->app->instance('core.middleware', []);
        $this->app->bind(\Illuminate\Database\Schema\Blueprint::class, Blueprint::class);

        $this->app->bind(ResourceRegistrar::class, function (Application $app) {
            return new CustomResourceRegistrar($app['router']);
        });

        $this->app->register(SettingServiceProvider::class);

        // Exception handling is configured in bootstrap/app.php.

        $this->app->singleton(Breadcrumb::class);

        $this->app->singleton(Manager::class, PanelSectionManager::class);

        $this->app->singleton(GlobalSearchableManagerContract::class, GlobalSearchableManager::class);

        $this->app->bind(MetaBoxInterface::class, MetaBoxRepository::class);

        $this->app->singleton('core.action', Action::class);

        $this->app->singleton('core.filter', Filter::class);

        $this->app->singleton(AdminWidgetContract::class, AdminWidget::class);

        $this->app->singleton('core.google-fonts', GoogleFonts::class);

        $this->registerRouteMacros();

        $this->prepareAliasesIfMissing();

        $this->overrideDefaultConfigs();

        Schema::defaultStringLength(191);
    }

    public function boot(): void
    {
        if (is_dir(core_path('../public/build/admin'))) {
            $this->publishes([core_path('../public/build/admin') => public_path('build/admin')], 'cms-public');
        }

        $this
            ->loadAndPublishConfigurations(['permissions', 'assets'])
            ->loadAndPublishViews()
            ->loadAnonymousComponents()
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->loadMigrations()
            ->publishAssets();

        $this->app['blade.compiler']->anonymousComponentPath($this->getViewsPath().'/components', 'core');

        $this->overridePackagesConfigs();

        $this->app->booted(function (): void {
            do_action(BASE_ACTION_INIT);
        });

        $this->registerDashboardMenus();

        $this->registerPanelSections();

        Paginator::useBootstrapFive();

        $this->forceSSL();

        $this->configureIni();

    }

    protected function registerDashboardMenus(): void
    {
        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-system')
                        ->priority(10000)
                        ->name('core/base::layouts.platform_admin')
                        ->icon('ti ti-user-shield')
                        ->route('system.index')
                        ->permission('core.system')
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-tools')
                        ->priority(9000)
                        ->name('core/base::layouts.tools')
                        ->icon('ti ti-tool')
                        ->permission('core.tools')
                );
        });
    }

    protected function registerPanelSections(): void
    {
        PanelSectionManagerFacade::group('system')->beforeRendering(function (): void {
            PanelSectionManagerFacade::setGroupName(trans('core/base::layouts.platform_admin'))
                ->register(SystemPanelSection::class);
        });
    }

    protected function configureIni(): void
    {
        $currentLimit = @ini_get('memory_limit');
        $currentLimitInt = Helper::convertHrToBytes($currentLimit);

        $baseConfig = $this->getBaseConfig();

        $memoryLimit = Arr::get($baseConfig, 'memory_limit');

        if (! $memoryLimit) {
            if (Helper::isIniValueChangeable('memory_limit') === false) {
                $memoryLimit = $currentLimit;
            } else {
                $memoryLimit = '256M';
            }
        }

        $limitInt = Helper::convertHrToBytes($memoryLimit);
        if ($currentLimitInt !== -1 && ($limitInt === -1 || $limitInt > $currentLimitInt)) {
            BaseHelper::iniSet('memory_limit', $memoryLimit);
        }

        $maxExecutionTime = Arr::get($baseConfig, 'max_execution_time');

        $currentExecutionTimeLimit = @ini_get('max_execution_time');

        if ($currentExecutionTimeLimit < $maxExecutionTime) {
            BaseHelper::iniSet('max_execution_time', $maxExecutionTime);
        }
    }

    protected function forceSSL(): void
    {
        $baseConfig = $this->getBaseConfig();

        $forceUrl = Arr::get($baseConfig, 'force_root_url');
        if (! empty($forceUrl)) {
            URL::useOrigin($forceUrl);
        }

        $forceSchema = Arr::get($baseConfig, 'force_schema');
        if (! empty($forceSchema)) {
            $this->app['request']->server->set('HTTPS', 'on');

            URL::forceScheme($forceSchema);
        }
    }

    protected function getBaseConfig(): array
    {
        return $this->getConfig()->get('core.base.general', []);
    }

    protected function getConfig(): Repository
    {
        return $this->app['config'];
    }

    protected function overrideDefaultConfigs(): void
    {
        $config = $this->getConfig();

        $config->set([
            'app.debug_blacklist' => [
                '_ENV' => [
                    'APP_KEY',
                    'ADMIN_DIR',
                    'DB_DATABASE',
                    'DB_USERNAME',
                    'DB_PASSWORD',
                    'REDIS_PASSWORD',
                    'MAIL_PASSWORD',
                    'PUSHER_APP_KEY',
                    'PUSHER_APP_SECRET',
                ],
                '_SERVER' => [
                    'APP_KEY',
                    'ADMIN_DIR',
                    'DB_DATABASE',
                    'DB_USERNAME',
                    'DB_PASSWORD',
                    'REDIS_PASSWORD',
                    'MAIL_PASSWORD',
                    'PUSHER_APP_KEY',
                    'PUSHER_APP_SECRET',
                ],
                '_POST' => [
                    'password',
                ],
            ],
            'debugbar.enabled' => $this->app->hasDebugModeEnabled() &&
                ! $this->app->runningInConsole() &&
                ! $this->app->environment(['testing', 'production']),
            'debugbar.capture_ajax' => false,
            'debugbar.remote_sites_path' => '',
            'core.base.general.google_fonts' => GoogleFonts::getFonts(),
        ]);

        if (
            ! $config->has('logging.channels.deprecations')
            && $this->app->isLocal()
            && $this->app->hasDebugModeEnabled()
        ) {
            $config->set([
                'logging.channels.deprecations' => [
                    'driver' => 'single',
                    'path' => storage_path('logs/php-deprecation-warnings.log'),
                ],
            ]);
        }
    }

    protected function overridePackagesConfigs(): void
    {
        $config = $this->getConfig();

        $baseConfig = $this->getBaseConfig();

        /**
         * @var SettingStore $setting
         */
        $setting = $this->app[SettingStore::class];
        $timezone = $setting->get('time_zone', $config->get('app.timezone'));
        $locale = $setting->get('locale', Arr::get($baseConfig, 'locale', $config->get('app.locale')));

        $this->app->setLocale($locale);

        if (in_array($timezone, DateTimeZone::listIdentifiers())) {
            date_default_timezone_set($timezone);
        }

        $config->set([
            'app.locale' => $locale,
            'app.timezone' => $timezone,
            'purifier.settings' => [
                ...$config->get('purifier.settings', []),
                ...Arr::get($baseConfig, 'purifier', []),
            ],
            'datatables-buttons.pdf_generator' => 'excel',
            'excel.exports.csv.use_bom' => true,
            'dompdf.public_path' => public_path(),
            'database.connections.mysql.strict' => Arr::get($baseConfig, 'db_strict_mode'),
            'excel.imports.ignore_empty' => true,
            'excel.imports.csv.input_encoding' => Arr::get($baseConfig, 'csv_import_input_encoding', 'UTF-8'),
        ]);
    }

    protected function registerRouteMacros(): void
    {
        Route::macro('wherePrimaryKey', function (array|string|null $name = 'id') {
            if (! $name) {
                $name = 'id';
            }

            /**
             * @var Route $this
             */
            if (BaseModel::determineIfUsingUuidsForId()) {
                return $this->whereUuid($name);
            }

            if (BaseModel::determineIfUsingUlidsForId()) {
                return $this->whereUlid($name);
            }

            return $this->whereNumber($name);
        });

        Route::macro('permission', function (array|string|bool|null $value) {
            /**
             * @var Route $this
             */
            $action = $this->getAction();
            Arr::set($action, 'permission', $value);

            return $this->setAction($action);
        });
    }

    protected function prepareAliasesIfMissing(): void
    {
        $aliasLoader = AliasLoader::getInstance();

        if (! class_exists('BaseHelper')) {
            $aliasLoader->alias('BaseHelper', BaseHelper::class);
            $aliasLoader->alias('DashboardMenu', DashboardMenu::class);
            $aliasLoader->alias('PageTitle', PageTitle::class);
        }

        if (! class_exists('Breadcrumb')) {
            $aliasLoader->alias('Breadcrumb', BreadcrumbFacade::class);
        }

        if (! class_exists('PanelSectionManager')) {
            $aliasLoader->alias('PanelSectionManager', PanelSectionManagerFacade::class);
        }

        if (! class_exists('AdminAppearance')) {
            $aliasLoader->alias('AdminAppearance', AdminAppearance::class);
        }

        if (! class_exists('AdminHelper')) {
            $aliasLoader->alias('AdminHelper', AdminHelper::class);
        }
    }
}
