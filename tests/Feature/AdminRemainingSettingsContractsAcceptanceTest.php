<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\Blog\Models\Post;
use Kern\Icon\Facades\Icon;
use Kern\Media\Models\MediaFile;
use Kern\PluginManagement\Services\PluginService;
use Kern\Slug\Models\Slug;
use Kern\Theme\Events\RenderingThemeOptionSettings;
use Kern\Theme\Facades\ThemeOption;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingSettingsContractsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->sourceRoot = base_path();
        $this->fixtureRoot = $this->sourceRoot.'/tmp/acceptance-settings-'.bin2hex(random_bytes(8));
        foreach (['platform/plugins', 'platform/themes', 'public/storage', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs', 'bootstrap/cache', 'lang', 'resources', 'config'] as $directory) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$directory);
        }
        File::copyDirectory(public_path('build'), $this->fixtureRoot.'/public/build');
        File::copy($this->sourceRoot.'/composer.json', $this->fixtureRoot.'/composer.json');
        Icon::setIconPath($this->sourceRoot.'/platform/core/icon/resources/svg');
        $this->app->setBasePath($this->fixtureRoot);
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->useBootstrapPath($this->fixtureRoot.'/bootstrap');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config([
            'packages.installer.installer.enabled' => false,
            'filesystems.default' => 'public',
            'core.media.media.disk' => 'public',
            'filesystems.disks.public.root' => $this->fixtureRoot.'/public/storage',
            'filesystems.disks.local.root' => $this->fixtureRoot.'/storage/private',
            'view.compiled' => $this->fixtureRoot.'/storage/framework/views',
            'purifier.cachePath' => $this->fixtureRoot.'/storage/purifier',
            'packages.plugin-management.general.enable_plugin_list_cache' => false,
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);
        $this->app->setBasePath($this->sourceRoot);
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function importPayload(string $imageUrl): array
    {
        File::copyDirectory($this->sourceRoot.'/platform/plugins/blog', plugin_path('blog'));
        $this->assertFalse(app(PluginService::class)->activate('blog')['error']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        Storage::disk('local')->put('data-synchronize/image.csv', "name,categories,tags,status,is_featured,image\nImported image row,Image category,Image tag,published,No,".$imageUrl."\n");

        return ['file_name' => 'image.csv', 'offset' => 0, 'limit' => 50];
    }

    public function test_image_url_import_downloads_real_bytes_and_keeps_taxonomy_attached_to_the_post(): void
    {
        Http::preventStrayRequests();
        $url = 'https://images.example.test/acceptance-'.bin2hex(random_bytes(8)).'.jpg';
        $image = UploadedFile::fake()->image('photo.jpg', 80, 60);
        $bytes = file_get_contents($image->getPathname());
        Http::fake([$url => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg'])]);
        $payload = $this->importPayload($url);
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.total', 1);
        $post = Post::query()->sole();
        $file = MediaFile::query()->sole();
        $this->assertSame($file->url, $post->image);
        $this->assertNotSame($url, $post->image);
        $this->assertSame([80, 60], array_slice(getimagesizefromstring(Storage::disk('public')->get($file->url)), 0, 2));
        $this->assertSame(['Image category'], $post->categories->pluck('name')->all());
        $this->assertSame(['Image tag'], $post->tags->pluck('name')->all());
        Http::assertSentCount(1);
    }

    #[TestWith([503, ''])]
    #[TestWith([200, '<html>Not an image</html>'])]
    public function test_failed_or_invalid_image_download_keeps_the_baseline_remote_url_without_orphan_taxonomy(int $status, string $body): void
    {
        Http::preventStrayRequests();
        $url = 'https://images.example.test/acceptance-'.bin2hex(random_bytes(8)).'.jpg';
        Http::fake([$url => Http::response($body, $status, ['Content-Type' => 'text/html'])]);
        $payload = $this->importPayload($url);
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.total', 1);
        $post = Post::query()->sole();
        $this->assertSame($url, $post->image);
        $this->assertDatabaseCount('media_files', 0);
        $this->assertSame(['Image category'], $post->categories->pluck('name')->all());
        $this->assertSame(['Image tag'], $post->tags->pluck('name')->all());
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('tags', 1);
        Http::assertSentCount(1);
    }

    public function test_real_bearer_token_abilities_and_revocation_are_enforced(): void
    {
        Route::middleware(['api', 'auth:sanctum', CheckAbilities::class.':acceptance.read'])->get('/api/acceptance-token/read', fn () => response()->json(['token_id' => request()->user()->currentAccessToken()->getKey()]));
        Route::middleware(['api', 'auth:sanctum', CheckAbilities::class.':acceptance.write'])->get('/api/acceptance-token/write', fn () => response()->json(['allowed' => true]));
        $admin = UserFactory::new()->superUser()->create();
        $this->actingAs($admin)->post(route('api.sanctum-token.store'), ['name' => 'Acceptance bearer'], $this->headers())->assertRedirect();
        $plaintext = session('plainTextToken');
        $token = PersonalAccessToken::query()->sole();
        $this->assertSame(['*'], $token->abilities);
        Auth::forgetGuards();
        $this->getJson('/api/acceptance-token/read', ['Authorization' => 'Bearer '.$plaintext])->assertOk()->assertJsonPath('token_id', $token->getKey());
        $token->update(['abilities' => ['acceptance.read']]);
        Auth::forgetGuards();
        $this->getJson('/api/acceptance-token/read', ['Authorization' => 'Bearer '.$plaintext])->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/acceptance-token/write', ['Authorization' => 'Bearer '.$plaintext])->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('api.sanctum-token.destroy', $token))->assertOk()->assertJsonPath('error', false);
        $this->assertModelMissing($token);
        Auth::forgetGuards();
        $this->getJson('/api/acceptance-token/read', ['Authorization' => 'Bearer '.$plaintext])->assertUnauthorized();
    }

    private function publicFixture(): void
    {
        $path = theme_path('acceptance-settings');
        foreach (['public', 'layouts', 'views', 'routes'] as $directory) {
            File::ensureDirectoryExists($path.'/'.$directory);
        }
        File::put($path.'/theme.json', json_encode(['id' => 'test/settings', 'name' => 'Acceptance settings', 'namespace' => 'Theme\\AcceptanceSettings\\', 'version' => '1.0', 'author' => 'Test', 'required_plugins' => []]));
        File::put($path.'/layouts/default.blade.php', '<!DOCTYPE html><html><body>{!! Theme::content() !!}</body></html>');
        File::put($path.'/views/page.blade.php', '<main>{!! BaseHelper::clean($page->content) !!}</main>');
        File::put($path.'/routes/web.php', '<?php Theme::routes();');
        File::ensureDirectoryExists(package_path('theme/routes'));
        File::copy($this->sourceRoot.'/platform/packages/theme/routes/public.php', package_path('theme/routes/public.php'));
        $this->assertFalse(app(ThemeService::class)->activate('acceptance-settings')['error']);
        $page = PageFactory::new()->create(['content' => '<p>Public after maintenance</p>', 'status' => 'published']);
        Slug::query()->create(['key' => 'maintenance-proof', 'prefix' => '', 'reference_type' => $page::class, 'reference_id' => $page->getKey()]);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_omitted_registered_theme_secret_preserves_its_saved_value(): void
    {
        $this->publicFixture();
        $this->actingAs(UserFactory::new()->superUser()->create());
        app('events')->listen(RenderingThemeOptionSettings::class, function (): void {
            ThemeOption::setSection(['id' => 'acceptance-general', 'title' => 'General', 'priority' => 1]);
            foreach (['acceptance_secret', 'acceptance_public'] as $name) {
                ThemeOption::setField(['id' => $name, 'section_id' => 'acceptance-general', 'type' => 'text', 'label' => $name, 'attributes' => ['name' => $name]]);
            }
        });
        ThemeOption::setOption('acceptance_secret', 'stored-fixture-secret');
        ThemeOption::saveOptions();
        $this->post(route('theme.options.post'), ['acceptance_public' => 'Updated public field'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('stored-fixture-secret', ThemeOption::getOption('acceptance_secret'));
        $this->assertSame('Updated public field', ThemeOption::getOption('acceptance_public'));
    }

    #[TestWith(['clear_cms_cache'])]
    #[TestWith(['refresh_compiled_views'])]
    #[TestWith(['clear_config_cache'])]
    #[TestWith(['clear_route_cache'])]
    #[TestWith(['clear_log'])]
    public function test_each_cache_operation_rejects_non_superuser_and_demo_without_file_effects(string $type): void
    {
        File::put(storage_path('logs/acceptance.log'), 'keep log');
        File::put($this->app->getCachedConfigPath(), '<?php return [];');
        File::put($this->app->getCachedRoutesPath(), '<?php return [];');
        File::put(config('view.compiled').'/acceptance.php', 'keep view');
        Cache::put('acceptance-cache', 'keep');
        $this->actingAs(UserFactory::new()->create(['permissions' => ['core.system' => true]]));
        $this->postJson(route('system.cache.clear'), ['type' => $type])->assertUnauthorized();
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.general.demo_mode_enabled' => true]);
        $this->postJson(route('system.cache.clear'), ['type' => $type])->assertJsonPath('error', true);
        $this->assertSame('keep', Cache::get('acceptance-cache'));
        $this->assertSame('keep log', File::get(storage_path('logs/acceptance.log')));
        $this->assertSame('<?php return [];', File::get($this->app->getCachedConfigPath()));
        $this->assertSame('<?php return [];', File::get($this->app->getCachedRoutesPath()));
        $this->assertSame('keep view', File::get(config('view.compiled').'/acceptance.php'));
    }

    #[TestWith(['clear_config_cache'])]
    #[TestWith(['clear_log'])]
    public function test_admin_and_public_still_render_after_actual_config_or_log_clear(string $type): void
    {
        $this->publicFixture();
        $this->actingAs(UserFactory::new()->superUser()->create());
        File::put($this->app->getCachedConfigPath(), '<?php return [];');
        File::put(storage_path('logs/acceptance.log'), 'remove log');
        $this->postJson(route('system.cache.clear'), ['type' => $type])->assertOk()->assertJsonPath('error', false);
        $this->assertFalse(File::exists($type === 'clear_config_cache' ? $this->app->getCachedConfigPath() : storage_path('logs/acceptance.log')));
        $this->get(route('dashboard.index'), $this->headers())->assertOk();
        $this->get('/maintenance-proof')->assertOk()->assertSee('Public after maintenance');
    }
}
