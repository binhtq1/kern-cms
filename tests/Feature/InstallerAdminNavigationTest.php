<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Kern\ACL\Models\User;
use Kern\Base\Services\ClearCacheService;
use Kern\PluginManagement\PluginManifest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class InstallerAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ripple_installation_enables_react_language_support_and_admin_navigation(): void
    {
        $directory = sys_get_temp_dir().'/kern-installer-navigation-'.uniqid();
        $originalPublicPath = public_path();
        foreach (['storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'public', 'lang', 'bootstrap/cache'] as $path) {
            File::ensureDirectoryExists($directory.'/'.$path);
        }

        $this->app->useStoragePath($directory.'/storage');
        $this->app->usePublicPath($directory.'/public');
        $this->app->useLangPath($directory.'/lang');
        $this->app->useBootstrapPath($directory.'/bootstrap');
        File::link($originalPublicPath.'/build', $directory.'/public/build');
        $this->app->instance(PluginManifest::class, new PluginManifest);
        // Exercise real dependency activation/migrations without touching shared caches.
        $this->mock(ClearCacheService::class)->shouldIgnoreMissing();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        config([
            'packages.installer.installer.enabled' => true,
            'packages.plugin-management.general.enable_plugin_list_cache' => false,
            'view.compiled' => $directory.'/storage/framework/views',
        ]);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);

        try {
            $this->assertFalse(is_plugin_active('language'));
            $this->assertFalse(is_plugin_active('language-advanced'));
            File::put($directory.'/storage/installing', now()->toDateTimeString());

            $this->post(URL::temporarySignedRoute('installers.themes.store', now()->addMinutes(30)), ['theme' => 'ripple'])
                ->assertSessionHasNoErrors()->assertRedirect();

            $this->assertTrue(is_plugin_active('language'));
            $this->assertTrue(is_plugin_active('language-advanced'));
            $this->assertTrue(config('plugins.language-advanced.general.page_use_language_v2'));
            $this->assertTrue(Schema::hasTable('pages_translations'));
            $this->assertTrue(Schema::hasTable('slugs_translations'));

            $response = $this->post(URL::temporarySignedRoute('installers.accounts.store', now()->addMinutes(30)), [
                'first_name' => 'Fresh',
                'last_name' => 'Admin',
                'username' => 'freshadmin',
                'email' => 'fresh-admin@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->get($response->headers->get('Location'))->assertOk();
            $this->assertTrue(User::query()->where('username', 'freshadmin')->firstOrFail()->isSuperUser());

            $headers = [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => hash_file('sha256', $originalPublicPath.'/build/admin/manifest.json'),
                'Accept' => 'text/html',
            ];
            foreach ([
                'dashboard.index' => 'Dashboard/Index',
                'pages.index' => 'Pages/Index',
                'posts.index' => 'Posts/Index',
                'categories.index' => 'Categories/Index',
                'tags.index' => 'Tags/Index',
                'galleries.index' => 'Galleries/Index',
            ] as $route => $component) {
                $this->get(route($route), $headers)->assertOk()->assertJsonPath('component', $component);
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
