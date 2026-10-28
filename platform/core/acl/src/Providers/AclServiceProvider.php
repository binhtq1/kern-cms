<?php

namespace Kern\ACL\Providers;

use Exception;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Arr;
use Kern\ACL\Http\Middleware\Authenticate;
use Kern\ACL\Http\Middleware\RedirectIfAuthenticated;
use Kern\ACL\Models\Activation;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\ACL\Repositories\Eloquent\ActivationRepository;
use Kern\ACL\Repositories\Eloquent\RoleRepository;
use Kern\ACL\Repositories\Eloquent\UserRepository;
use Kern\ACL\Repositories\Interfaces\ActivationInterface;
use Kern\ACL\Repositories\Interfaces\RoleInterface;
use Kern\ACL\Repositories\Interfaces\UserInterface;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\PanelSections\System\SystemPanelSection;
use Kern\Base\Supports\AdminAppearance;
use Kern\Base\Supports\Helper;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;

class AclServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(UserInterface::class, function () {
            return new UserRepository(new User);
        });

        $this->app->bind(ActivationInterface::class, function () {
            return new ActivationRepository(new Activation);
        });

        $this->app->bind(RoleInterface::class, function () {
            return new RoleRepository(new Role);
        });
    }

    public function boot(): void
    {
        $this->app->register(CommandServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        $this->setNamespace('core/acl')
            ->loadHelpers()
            ->loadAndPublishConfigurations(['general', 'permissions', 'email'])
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets()
            ->loadRoutes()
            ->loadMigrations();

        $this->garbageCollect();

        $this->app['events']->listen(RouteMatched::class, function (): void {
            $router = $this->app['router'];

            $router->aliasMiddleware('auth', Authenticate::class);
            $router->aliasMiddleware('guest', RedirectIfAuthenticated::class);
        });

        $this->registerPanelSections();

        $this->app->booted(function (): void {
            config()->set(['auth.providers.users.model' => User::class]);

            EmailHandler::addTemplateSettings('acl', config('core.acl.email', []), 'core');

            $this->app->register(HookServiceProvider::class);

        });
    }

    protected function registerPanelSections(): void
    {
        PanelSectionManager::group('system')
            ->beforeRendering(function (): void {
                PanelSectionManager::registerItems(
                    SystemPanelSection::class,
                    fn () => [
                        PanelSectionItem::make('users')
                            ->setTitle(trans('core/acl::users.users'))
                            ->withIcon('ti ti-users')
                            ->withDescription(trans('core/acl::users.users_description'))
                            ->withPriority(-9999)
                            ->withRoute('users.index'),
                        PanelSectionItem::make('roles')
                            ->setTitle(trans('core/acl::permissions.role_permission'))
                            ->withIcon('ti ti-users-group')
                            ->withDescription(trans('core/acl::permissions.role_permission_description'))
                            ->withPriority(-9980)
                            ->withRoute('roles.index'),
                    ]
                );
            });
    }

    protected function getLoginPageBackgroundUrl(): string
    {
        return app(AdminAppearance::class)->getLoginPageBackgroundUrl() ?? '';
    }

    /**
     * Garbage collect activations and reminders.
     */
    protected function garbageCollect(): void
    {
        if ($this->app->runningInConsole() || ! Helper::isConnectedDatabase()) {
            return;
        }

        $config = $this->app->make('config')->get('core.acl.general');

        $this->sweep($this->app->make(ActivationInterface::class), Arr::get($config, 'activations.lottery', [2, 100]));
    }

    protected function sweep(ActivationInterface $repository, array $lottery): void
    {
        if ($this->configHitsLottery($lottery)) {
            try {
                $repository->removeExpired();
            } catch (Exception $exception) {
                BaseHelper::logError($exception);
            }
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     */
    protected function configHitsLottery(array $lottery): bool
    {
        return mt_rand(1, $lottery[1]) <= $lottery[0];
    }
}
