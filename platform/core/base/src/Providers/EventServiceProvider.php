<?php

namespace Kern\Base\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Kern\ACL\Events\RoleAssignmentEvent;
use Kern\ACL\Events\RoleUpdateEvent;
use Kern\Base\Events\AdminNotificationEvent;
use Kern\Base\Events\BeforeEditContentEvent;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\PanelSectionsRendering;
use Kern\Base\Events\SendMailEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Events\UpdatedEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\MetaBox;
use Kern\Base\Http\Middleware\AdminLocaleMiddleware;
use Kern\Base\Http\Middleware\CoreMiddleware;
use Kern\Base\Http\Middleware\DisableInDemoModeMiddleware;
use Kern\Base\Http\Middleware\HttpsProtocolMiddleware;
use Kern\Base\Http\Middleware\LocaleMiddleware;
use Kern\Base\Listeners\AdminNotificationListener;
use Kern\Base\Listeners\BeforeEditContentListener;
use Kern\Base\Listeners\ClearDashboardMenuCaches;
use Kern\Base\Listeners\ClearDashboardMenuCachesForLoggedUser;
use Kern\Base\Listeners\CreatedContentListener;
use Kern\Base\Listeners\DeletedContentListener;
use Kern\Base\Listeners\PushDashboardMenuToSystemPanel;
use Kern\Base\Listeners\SendMailListener;
use Kern\Base\Listeners\UpdatedContentListener;
use Kern\Dashboard\Events\RenderingDashboardWidgets;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SendMailEvent::class => [
            SendMailListener::class,
        ],
        CreatedContentEvent::class => [
            CreatedContentListener::class,
        ],
        UpdatedContentEvent::class => [
            UpdatedContentListener::class,
        ],
        DeletedContentEvent::class => [
            DeletedContentListener::class,
        ],
        BeforeEditContentEvent::class => [
            BeforeEditContentListener::class,
        ],
        AdminNotificationEvent::class => [
            AdminNotificationListener::class,
        ],
        UpdatedEvent::class => [
            ClearDashboardMenuCaches::class,
        ],
        Login::class => [
            ClearDashboardMenuCachesForLoggedUser::class,
        ],
        RoleAssignmentEvent::class => [
            ClearDashboardMenuCaches::class,
        ],
        RoleUpdateEvent::class => [
            ClearDashboardMenuCaches::class,
        ],
    ];

    public function boot(): void
    {
        $events = $this->app['events'];

        $events->listen(RouteMatched::class, function (): void {
            /**
             * @var Router $router
             */
            $router = $this->app['router'];

            $router->pushMiddlewareToGroup('web', LocaleMiddleware::class);
            $router->pushMiddlewareToGroup('web', AdminLocaleMiddleware::class);
            $router->pushMiddlewareToGroup('web', HttpsProtocolMiddleware::class);
            $router->aliasMiddleware('preventDemo', DisableInDemoModeMiddleware::class);
            $router->middlewareGroup('core', [CoreMiddleware::class]);

            add_action(BASE_ACTION_META_BOXES, [MetaBox::class, 'doMetaBoxes'], 8, 2);

        });

        $events->listen(MigrationsStarted::class, function (): void {
            rescue(function (): void {
                if (DB::getDefaultConnection() === 'mysql') {
                    DB::statement('SET SESSION sql_require_primary_key=0');
                }
            }, report: false);
        });

        $events->listen(['cache:cleared'], function (): void {
            $files = $this->app['files'];

            $files->delete(storage_path('cache_keys.json'));

            $files->deleteDirectory(storage_path('app/chunks'));
            $files->deleteDirectory(storage_path('app/data-synchronize'));
        });

        $events->listen(PanelSectionsRendering::class, PushDashboardMenuToSystemPanel::class);

        if ($this->app->isLocal()) {
            DB::listen(function (QueryExecuted $queryExecuted): void {
                if ($queryExecuted->time < 500) {
                    return;
                }

                Log::warning(sprintf('DB query exceeded %s ms. SQL: %s', $queryExecuted->time, $queryExecuted->sql));
            });
        }

        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            add_filter(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, function (?string $html) {

                $size = File::size(storage_path('framework/cache'));

                if ($size < 1024 * 1024 * 100) {
                    return $html;
                }

                $size = BaseHelper::humanFilesize($size);

                $notice = view('core/base::system.partials.cache-too-large-alert', compact('size'))->render();
                request()->attributes->set('admin_dashboard_supported_notices', [
                    ...request()->attributes->get('admin_dashboard_supported_notices', []), $notice,
                ]);

                return $html.$notice;
            }, 5);
        });
    }
}
