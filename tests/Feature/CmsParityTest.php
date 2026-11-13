<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Database\Seeders\RippleDemoSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Kern\Base\Exceptions\DisabledInDemoModeException;
use Kern\Media\Facades\RvMedia;
use Kern\Page\Models\Page;
use Kern\PluginManagement\PluginManifest;
use Kern\PluginManagement\Services\PluginService;
use Kern\RequestLog\Models\RequestLog;
use Kern\Setting\Facades\Setting;
use Kern\Theme\Providers\RouteServiceProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CmsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
    }

    public function test_plugin_manifest_does_not_load_a_different_plugin_with_the_same_count(): void
    {
        app(PluginService::class)->activate('contact');
        $directory = sys_get_temp_dir().'/kern-plugin-manifest-'.uniqid();
        File::ensureDirectoryExists($directory.'/cache');
        $bootstrapPath = $this->app->bootstrapPath();
        $this->app->useBootstrapPath($directory);

        try {
            $manifest = new PluginManifest;
            $manifest->generateManifest([
                'namespaces' => ['block' => 'Kern\\Block\\'],
                'providers' => ['Kern\\Block\\Providers\\BlockServiceProvider'],
            ]);
            $result = $manifest->getManifest();
            $this->assertSame(['contact'], array_keys($result['namespaces']));
            $this->assertSame(['Kern\\Contact\\Providers\\ContactServiceProvider'], $result['providers']);
        } finally {
            $this->app->useBootstrapPath($bootstrapPath);
            File::deleteDirectory($directory);
        }
    }

    public function test_missing_pages_are_logged_with_their_referrer_and_repeat_count(): void
    {
        app(PluginService::class)->activate('request-log');
        Route::get('/parity-missing', fn () => abort(404));
        $this->get('/parity-missing', ['Referer' => 'http://localhost/source'])->assertNotFound();
        $this->get('/parity-missing')->assertNotFound();

        $log = RequestLog::query()->firstOrFail();
        $this->assertSame(2, $log->count);
        $this->assertSame(404, $log->status_code);
        $this->assertContains('http://localhost/source', $log->referrer);
    }

    public function test_http_errors_keep_the_cms_json_contract_and_protocol_headers(): void
    {
        Route::get('/parity-get-only', fn () => 'OK');
        $this->postJson('/parity-get-only')->assertStatus(405)->assertJsonPath('error', true)
            ->assertHeader('Allow', 'GET, HEAD');
        $this->getJson('/parity-missing')->assertNotFound()->assertJsonPath('error', true);
        $this->get('/api/parity-missing')->assertNotFound()->assertJsonPath('error', true);
    }

    public function test_admin_error_pages_and_theme_error_view_filters_are_used(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        Route::get('/admin/parity-missing', fn () => abort(404));
        $adminNotFound = $this->get('/admin/parity-missing')->assertNotFound()->assertViewIs('core/base::admin.app');
        $adminNotFound->assertInertia(fn ($page) => $page
            ->component('Pages/Error', false)
            ->where('status', 404));
        add_filter('get_http_exception_view', fn () => 'core/base::errors.404', 999);
        Route::get('/parity-forbidden', fn () => abort(403));
        $this->get('/parity-forbidden')->assertForbidden()->assertSee('Take me home');
        config(['app.debug' => false]);
        Route::get('/admin/parity-server-error', fn () => throw new RuntimeException('Private server error'));
        $adminServerError = $this->get('/admin/parity-server-error')
            ->assertStatus(500)
            ->assertViewIs('core/base::admin.app')
            ->assertDontSee('Private server error');
        $adminServerError->assertInertia(fn ($page) => $page
            ->component('Pages/Error', false)
            ->where('status', 500)
            ->where('message', fn (string $message): bool => ! str_contains($message, 'Private server error')));
    }

    public function test_email_transport_errors_have_a_friendly_response(): void
    {
        Route::get('/parity-mail-error', fn () => throw new TransportException('Internal SMTP credentials failure'));
        $this->getJson('/parity-mail-error')->assertStatus(500)->assertJsonPath('error', true)
            ->assertDontSee('Internal SMTP credentials failure');
    }

    public function test_error_redirects_and_authentication_filters_keep_json_statuses(): void
    {
        Setting::forceSet('redirect_404_to_homepage', true)->save();
        $this->get('/parity-missing')->assertRedirect('/');
        $this->getJson('/parity-missing')->assertNotFound()->assertJsonPath('error', true);
        add_filter('cms_unauthenticated_response', fn () => redirect('/custom-sign-in'));
        Route::get('/parity-member-only', fn () => throw new AuthenticationException('Unauthenticated.', ['member']));
        $this->get('/parity-member-only')->assertRedirect('/custom-sign-in');
        $this->getJson('/parity-member-only')->assertUnauthorized()->assertJsonPath('error', true);
        Route::get('/parity-demo-disabled', fn () => throw new DisabledInDemoModeException);
        $this->getJson('/parity-demo-disabled')->assertForbidden()->assertJsonPath('error', true);
    }

    public function test_error_report_throttling_does_not_suppress_application_logs(): void
    {
        Log::spy();
        Cache::put('send_error_exception', true, 300);
        report(new RuntimeException('First error'));
        report(new RuntimeException('Second error'));
        Log::shouldHaveReceived('error')->twice();
    }

    public function test_the_installer_can_select_a_bundled_theme_with_signed_requests(): void
    {
        $directory = sys_get_temp_dir().'/kern-theme-install-'.uniqid();
        File::ensureDirectoryExists($directory);
        $this->app->useStoragePath($directory);
        File::put($directory.'/installing', now()->toDateTimeString());
        config(['packages.installer.installer.enabled' => true]);
        Http::preventStrayRequests();

        try {
            $this->get(URL::temporarySignedRoute('installers.themes.index', now()->addMinutes(30)))
                ->assertOk()->assertSee('Ripple');
            $this->post('/install/themes', ['theme' => 'ripple'])->assertForbidden();
            $this->postJson(URL::temporarySignedRoute('installers.themes.store', now()->addMinutes(30)), ['theme' => '../invalid'])
                ->assertUnprocessable()->assertJsonValidationErrors('theme');
            $response = $this->post(URL::temporarySignedRoute('installers.themes.store', now()->addMinutes(30)), ['theme' => 'ripple']);
            $response->assertSessionHasNoErrors()->assertRedirect();
            $this->assertStringContainsString('/install/accounts?', $response->headers->get('Location'));
            $this->assertSame('ripple', setting('theme'));
            $this->assertContains('blog', get_active_plugins());
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_ripple_demo_restores_content_menus_widgets_and_theme_options(): void
    {
        Http::preventStrayRequests();
        Storage::fake('public');
        $user = UserFactory::new()->superUser()->create();
        Setting::forceSet('parity_existing_setting', 'keep')->save();
        $this->seed(RippleDemoSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame('keep', setting('parity_existing_setting'));
        $this->assertDatabaseCount('pages', 5);
        $this->assertDatabaseCount('posts', 20);
        $this->assertDatabaseCount('galleries', 15);
        $this->assertDatabaseCount('members', 11);
        $this->assertDatabaseCount('contacts', 10);
        $this->assertDatabaseCount('blocks', 5);
        $this->assertDatabaseHas('languages', ['lang_locale' => 'en', 'lang_is_default' => true]);
        $this->assertDatabaseCount('menus', 2);
        $this->assertDatabaseCount('widgets', 7);
        $this->assertDatabaseHas('pages', ['name' => 'Homepage', 'template' => 'no-sidebar']);
        $this->assertSame('Kern CMS', theme_option('site_title'));
        Storage::disk('public')->assertExists(['general/logo.svg', 'general/favicon.svg', 'news/1.jpg']);

        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->get('/')->assertOk()->assertSee('What&#039;s new?', false);
        config(['core.base.admin.legacy_mode' => true]);
        $this->actingAs($user)->get(route('pages.create'))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Pages/Form', false));
        config(['plugins.language-advanced.general.page_use_language_v2' => false]);
        $pageCreate = $this->actingAs($user)->get(route('pages.create'))
            ->assertStatus(503)
            ->assertViewIs('core/base::admin.app');
        $pageCreate->assertInertia(fn ($page) => $page
            ->component('System/Compatibility', false)
            ->where('message', fn (string $message): bool => str_contains($message, 'legacy Page language mode')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing data found');
        $this->seed(RippleDemoSeeder::class);
    }

    public function test_animated_gif_uploads_keep_the_original_and_create_static_thumbnails(): void
    {
        Storage::fake('public');
        $source = database_path('seeders/files/general/preloader.gif');
        $result = RvMedia::uploadFromPath($source);
        $this->assertFalse($result['error'], $result['message'] ?? 'GIF upload failed');
        $url = $result['data']->url;
        $this->assertSame(hash_file('sha256', $source), hash('sha256', Storage::disk('public')->get($url)));
        $thumbnail = str_replace('.gif', '-150x150.gif', $url);
        Storage::disk('public')->assertExists($thumbnail);
        $image = RvMedia::imageManager()->read(Storage::disk('public')->path($thumbnail));
        $this->assertFalse($image->isAnimated());
        $this->assertSame(150, $image->width());
    }

    public function test_ripple_demo_refuses_to_replace_existing_content(): void
    {
        UserFactory::new()->superUser()->create();
        $page = Page::query()->create(['name' => 'Existing page', 'status' => 'published']);
        try {
            $this->seed(RippleDemoSeeder::class);
            $this->fail('Existing content must not be replaced.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Existing data found in pages', $exception->getMessage());
        }
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'name' => 'Existing page']);
        $this->assertDatabaseCount('pages', 1);
        $this->assertSame([], get_active_plugins());
    }
}
