<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache as LaravelCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Models\Contact;
use Kern\Language\Commands\RouteCacheCommand;
use Kern\PluginManagement\Services\PluginService;
use Kern\Support\Services\Cache\Cache;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PluginThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        Http::preventStrayRequests();
    }

    public function test_every_bundled_plugin_can_be_activated_and_admin_screens_render(): void
    {
        $this->withoutExceptionHandling();
        $service = app(PluginService::class);
        foreach (get_installed_plugins() as $plugin) {
            $result = $service->activate($plugin);
            $this->assertFalse($result['error'], $plugin.': '.$result['message']);
        }
        $this->assertCount(16, get_active_plugins());
        foreach (Route::getRoutes() as $route) {
            $controller = ltrim($route->getControllerClass() ?? '', '\\');
            if (str_starts_with($controller, 'Kern\\') || str_starts_with($controller, 'Theme\\')) {
                $this->assertTrue(class_exists($controller), $controller);
                $this->assertTrue(method_exists($controller, $route->getActionMethod()), $controller.'::'.$route->getActionMethod());
            }
        }

        $this->actingAs(UserFactory::new()->superUser()->create());

        foreach (['plugins.index', 'posts.index', 'categories.index', 'tags.index', 'galleries.index', 'contacts.index', 'member.index', 'block.index', 'languages.index', 'theme.index'] as $route) {
            $response = $this->get(route($route));
            $this->assertSame(200, $response->status(), $route.': '.substr(strip_tags($response->getContent()), 0, 700));
        }
    }

    public function test_local_plugin_controls_work_without_marketplace_routes(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('plugins.index'))->assertOk()->assertDontSee('marketplace', false);
        $this->putJson(route('plugins.change.status'), ['name' => 'block'])
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.status', 'activated');
        $this->assertContains('block', get_active_plugins());
        $this->putJson(route('plugins.change.status'), ['name' => 'block'])
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.status', 'deactivated');
        $this->assertNotContains('block', get_active_plugins());
        $this->get('/admin/plugins/marketplace/ajax/plugins')->assertNotFound();
        $this->putJson(route('plugins.change.status'), ['name' => '../base'])->assertUnprocessable();
    }

    public function test_ripple_activation_installs_its_local_dependencies(): void
    {
        $result = app(ThemeService::class)->activate('ripple');
        $this->assertFalse($result['error'], $result['message']);
        $this->assertSame('ripple', setting('theme'));
        foreach (['blog', 'contact', 'gallery', 'language', 'member'] as $plugin) {
            $this->assertContains($plugin, get_active_plugins());
        }
        $this->assertFileExists(public_path('themes/ripple/css/style.css'));
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $manifest = json_decode(file_get_contents(public_path('themes/ripple/build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $response = $this->get('/')->assertOk();
        $response->assertSee('themes/ripple/build/'.$manifest['platform/themes/ripple/assets/sass/style.scss']['file'], false)
            ->assertSee('themes/ripple/build/'.$manifest['platform/themes/ripple/assets/js/ripple.js']['file'], false)
            ->assertDontSee('jquery', false);
        $this->assertFileDoesNotExist(public_path('themes/ripple/plugins/jquery/jquery-3.7.1.min.js'));
        $this->assertFileDoesNotExist(public_path('themes/ripple/js/custom.min.js'));
        $this->get('/missing-kern-page')->assertNotFound();
    }

    public function test_contact_notifications_render_from_serialized_cache_and_refresh_when_read(): void
    {
        config(['core.base.admin.modules.maintenance' => 'blade']);
        app(PluginService::class)->activate('contact');
        $directory = sys_get_temp_dir().'/kern-contact-cache-'.uniqid();
        config(['cache.default' => 'file', 'cache.stores.file.path' => $directory, 'cache.stores.file.lock_path' => $directory]);
        LaravelCache::purge('file');

        try {
            $this->actingAs(UserFactory::new()->superUser()->create());
            $contact = Contact::query()->create([
                'name' => 'Unread cache notification',
                'email' => 'unread@example.test',
                'content' => 'A new message',
                'status' => ContactStatusEnum::UNREAD,
            ]);
            Contact::query()->create([
                'name' => 'Already read notification',
                'email' => 'read@example.test',
                'content' => 'An old message',
                'status' => ContactStatusEnum::READ,
            ]);

            $this->get(route('plugins.index'))->assertOk()->assertSee('Unread cache notification')
                ->assertDontSee('Already read notification');
            $this->get(route('plugins.index'))->assertOk()->assertSee('Unread cache notification');

            $contact->update(['status' => ContactStatusEnum::READ]);
            $this->get(route('plugins.index'))->assertOk()->assertDontSee('Unread cache notification');
            $this->get(route('plugins.index'))->assertOk()->assertDontSee('Unread cache notification');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_contact_notifications_recover_from_a_legacy_cached_paginator(): void
    {
        config(['core.base.admin.modules.maintenance' => 'blade']);
        app(PluginService::class)->activate('contact');
        $directory = sys_get_temp_dir().'/kern-contact-cache-'.uniqid();
        config(['cache.default' => 'file', 'cache.stores.file.path' => $directory, 'cache.stores.file.lock_path' => $directory]);
        LaravelCache::purge('file');

        try {
            $this->actingAs(UserFactory::new()->superUser()->create());
            Contact::query()->create([
                'name' => 'Legacy cache notification',
                'email' => 'legacy@example.test',
                'content' => 'A cached message',
                'status' => ContactStatusEnum::UNREAD,
            ]);
            Cache::make(Contact::class)->put('unread-contacts', Contact::query()->paginate(10), 3600);

            $this->get(route('plugins.index'))->assertOk()->assertSee('Legacy cache notification');
            $this->get(route('plugins.index'))->assertOk()->assertSee('Legacy cache notification');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_language_route_cache_preserves_canonical_names_and_executable_routes(): void
    {
        app(PluginService::class)->activate('language');
        $this->artisan('route:list', ['--path' => 'admin/pages'])->assertSuccessful();
        $directory = sys_get_temp_dir().'/kern-route-cache-'.uniqid();
        File::ensureDirectoryExists($directory.'/cache');
        $bootstrapPath = $this->app->bootstrapPath();
        $this->app->useBootstrapPath($directory);

        $command = new class(app('files')) extends RouteCacheCommand
        {
            protected function getFreshApplicationRoutesForLocale(?string $locale = null, bool $force = false): RouteCollection
            {
                $routes = new RouteCollection;
                $uri = $locale ? $locale.'/route-cache-test' : 'route-cache-test';
                $routes->add((new RoutingRoute('GET', $uri, fn () => 'Cached route works'))->name('cache.test'));

                return $routes;
            }
        };

        try {
            app(Kernel::class)->registerCommand($command);
            $this->artisan('route:cache')->assertSuccessful();
            require $this->app->getCachedRoutesPath();

            $this->assertSame('/route-cache-test', route('cache.test', [], false));
            $this->get('/route-cache-test')->assertOk()->assertSee('Cached route works');
            $this->get('/en/route-cache-test')->assertOk()->assertSee('Cached route works');
            $this->artisan('route:clear')->assertSuccessful();
            $this->assertFileDoesNotExist($this->app->getCachedRoutesPath());
        } finally {
            $this->app->useBootstrapPath($bootstrapPath);
            File::deleteDirectory($directory);
        }
    }
}
