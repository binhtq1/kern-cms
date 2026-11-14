<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Kern\ACL\Models\User;
use Kern\Analytics\Abstracts\AnalyticsAbstract;
use Kern\Blog\Models\Post;
use Kern\Icon\Facades\Icon;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Slug\Facades\SlugHelper;
use Kern\Slug\Models\Slug;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingSettingsBehaviorAcceptanceTest extends TestCase
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
        Http::preventStrayRequests();
        $this->actingAs(UserFactory::new()->superUser()->create());
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

    private function activatePlugin(string $plugin): void
    {
        File::copyDirectory($this->sourceRoot.'/platform/plugins/'.$plugin, plugin_path($plugin));
        $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
    }

    private function publicTheme(): void
    {
        $path = theme_path('acceptance-settings');
        foreach (['public', 'layouts', 'views', 'routes'] as $directory) {
            File::ensureDirectoryExists($path.'/'.$directory);
        }
        File::put($path.'/theme.json', json_encode(['id' => 'test/settings', 'name' => 'Acceptance settings', 'namespace' => 'Theme\\AcceptanceSettings\\', 'version' => '1.0', 'author' => 'Test', 'required_plugins' => []]));
        File::put($path.'/layouts/default.blade.php', '<!DOCTYPE html><html><head>{!! Theme::header() !!}</head><body>{!! Theme::content() !!}</body></html>');
        File::put($path.'/views/page.blade.php', '<main>{!! BaseHelper::clean($page->content) !!}</main>');
        File::put($path.'/views/post.blade.php', '<main>{!! BaseHelper::clean($post->content) !!}</main>');
        File::put($path.'/routes/web.php', '<?php Theme::routes();');
        File::ensureDirectoryExists(package_path('theme/routes'));
        File::copy($this->sourceRoot.'/platform/packages/theme/routes/public.php', package_path('theme/routes/public.php'));
        $this->assertFalse(app(ThemeService::class)->activate('acceptance-settings')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
    }

    private function saveMedia(array $changes = []): void
    {
        $payload = ['media_driver' => 'public', 'media_chunk_size' => 2, 'media_max_file_size' => 20, 'media_upload_path' => 'storage', 'media_enable_thumbnail_sizes' => 0, 'media_keep_original_file_size_and_quality' => 1];
        foreach (array_keys(RvMedia::getSizes()) as $size) {
            $payload['media_sizes_'.$size.'_width'] = 80;
            $payload['media_sizes_'.$size.'_height'] = 60;
        }
        $this->put(route('settings.media.update'), array_replace($payload, $changes), $this->headers())->assertRedirect()->assertSessionHasNoErrors();
    }

    private function upload(UploadedFile $image, int $folder = 0): MediaFile
    {
        $response = $this->postJson(route('media.picker-upload'), ['file' => $image, 'folder_id' => $folder])->assertOk()->assertJsonPath('error', false);

        return MediaFile::query()->findOrFail($response->json('data.id'));
    }

    #[TestWith([0, 'anh-dep'])]
    #[TestWith([1, 'ảnh-đẹp'])]
    public function test_permalink_suffix_and_latin_setting_save_and_resolve_the_actual_public_page(int $noLatin, string $slug): void
    {
        $this->publicTheme();
        $this->put(route('slug.settings.update'), ['public_single_ending_url' => '.html', 'slug_turn_off_automatic_url_translation_into_latin' => $noLatin], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('slug.settings'), $this->headers())->assertOk();
        $this->assertSame('.html', SlugHelper::getPublicSingleEndingURL());
        $this->assertSame((bool) $noLatin, SlugHelper::turnOffAutomaticUrlTranslationIntoLatin());
        $generated = $this->postJson(route('slug.create'), ['value' => 'Ảnh đẹp', 'model' => Page::class, 'slug_id' => '0'])->assertOk()->assertContent($slug)->getContent();
        $this->post(route('pages.store'), ['name' => 'Ảnh đẹp', 'content' => '<p>Permalink proof</p>', 'status' => 'published', 'slug' => $generated, 'is_slug_editable' => 1], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $page = Page::query()->sole();

        $this->assertSame($slug, $page->slugable->key);
        $this->assertSame(url('/'.$slug.'.html'), $page->url);
        $this->get($page->url)->assertOk()->assertSee('Permalink proof');
    }

    #[TestWith([1, 'Article'])]
    #[TestWith([1, 'BlogPosting'])]
    #[TestWith([0, 'NewsArticle'])]
    public function test_blog_schema_settings_control_json_ld_in_the_actual_public_post(int $enabled, string $type): void
    {
        setting()->forceSet('blog_post_schema_enabled', $enabled)->save();
        $this->publicTheme();
        $this->activatePlugin('blog');
        $this->put(route('blog.settings.update'), ['blog_post_schema_enabled' => $enabled, 'blog_post_schema_type' => $type], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $post = Post::query()->create(['name' => 'Schema proof', 'description' => 'Schema description', 'content' => '<p>Schema body</p>', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        Slug::query()->create(['key' => 'schema-proof', 'prefix' => SlugHelper::getPrefix(Post::class), 'reference_type' => Post::class, 'reference_id' => $post->getKey()]);

        $response = $this->get($post->url)->assertOk()->assertSee('Schema body');
        preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $response->getContent(), $matches);
        $schemas = collect($matches[1])->map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR));
        $article = $schemas->first(fn (array $schema): bool => ($schema['headline'] ?? null) === 'Schema proof');
        if ($enabled) {
            $this->assertSame($type, $article['@type'] ?? null);
            $this->assertSame($post->url, $article['mainEntityOfPage']['@id']);
        } else {
            $this->assertNull($article);
        }
    }

    public function test_analytics_valid_json_upload_save_masked_reopen_and_dashboard_use_saved_configuration(): void
    {
        $this->activatePlugin('analytics');
        $credential = json_encode(['type' => 'service_account', 'project_id' => 'acceptance-project', 'private_key_id' => 'fixture-id', 'private_key' => 'fixture-private-key', 'client_email' => 'analytics@example.test', 'client_id' => '123', 'auth_uri' => 'https://accounts.example.test/auth', 'token_uri' => 'https://accounts.example.test/token', 'auth_provider_x509_cert_url' => 'https://accounts.example.test/certs', 'client_x509_cert_url' => 'https://accounts.example.test/client'], JSON_THROW_ON_ERROR);
        $upload = $this->postJson(route('analytics.settings.json'), ['json' => UploadedFile::fake()->createWithContent('credentials.json', $credential)])->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.content', $credential);
        $this->put(route('analytics.settings.update'), ['analytics_dashboard_widgets' => 1, 'analytics_property_id' => '123456789', 'analytics_service_account_credentials' => $upload->json('data.content')], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $fields = collect($this->get(route('analytics.settings'), $this->headers())->assertOk()->json('props.fields'))->keyBy('name');
        $this->assertSame('123456789', $fields['analytics_property_id']['value']);
        $this->assertSame('', $fields['analytics_service_account_credentials']['value']);
        $this->assertTrue($fields['analytics_service_account_credentials']['stored']);
        $this->put(route('analytics.settings.update'), ['analytics_dashboard_widgets' => 1, 'analytics_property_id' => '987654321'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($credential, setting('analytics_service_account_credentials'));
        $this->assertSame('987654321', setting('analytics_property_id'));
        $analytics = $this->mock(AnalyticsAbstract::class);
        $analytics->shouldReceive('performQuery')->times(3)->andReturn(collect());
        $this->getJson(route('analytics.general', ['admin_json' => 1]))->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.stats.Sessions', 0);
        $widgets = collect($this->get(route('dashboard.index'), $this->headers())->assertOk()->json('props.widgets'))->filter(fn (array $widget): bool => str_starts_with($widget['name'], 'widget_analytics_'));
        $this->assertCount(4, $widgets);
        config(['core.base.general.demo_mode_enabled' => true]);
        $response = $this->get(route('analytics.settings'), $this->headers())->assertOk();
        $names = array_column($response->json('props.fields'), 'name');
        $this->assertNotContains('analytics_service_account_credentials', $names);
        $this->assertNotContains('analytics_credentials_upload', $names);
        $this->assertStringNotContainsString('fixture-private-key', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_local_media_save_preserves_masked_secrets_then_upload_preview_and_download_work(): void
    {
        setting()->forceSet('media_aws_secret_key', 'stored-acceptance-secret')->save();
        $this->saveMedia();
        $response = $this->get(route('settings.media'), $this->headers())->assertOk();
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $this->assertSame('public', $fields['media_driver']['value']);
        $this->assertSame('', $fields['media_aws_secret_key']['value']);
        $this->assertTrue($fields['media_aws_secret_key']['stored']);
        $this->assertStringNotContainsString('stored-acceptance-secret', $response->getContent());
        $this->assertSame('stored-acceptance-secret', setting('media_aws_secret_key'));
        $image = UploadedFile::fake()->image('saved-local.png', 90, 70);
        $bytes = $image->getContent();
        $file = $this->upload($image);
        $this->assertSame($bytes, Storage::disk('public')->get($file->url));
        $this->getJson(route('media.picker', ['path' => $file->url]))->assertOk()->assertJsonPath('data.0.src', RvMedia::url($file->url));
        Http::fake([RvMedia::url($file->url) => Http::response(Storage::disk('public')->get($file->url), 200, ['Content-Type' => 'image/png'])]);
        $this->get($file->indirect_url)->assertOk()->assertStreamedContent($bytes);
        $this->postJson(route('media.download'), ['selected' => [['id' => (string) $file->getKey(), 'is_folder' => false]]])->assertOk()->assertDownload('saved-local.png')->assertStreamedContent($bytes);
        $this->saveMedia();
        $this->assertSame($bytes, Storage::disk('public')->get($file->url));
        $this->assertSame('stored-acceptance-secret', setting('media_aws_secret_key'));
    }

    #[TestWith([1, 0, 0, 'Ảnh đẹp.png'])]
    #[TestWith([0, 0, 0, 'anh-dep.png'])]
    #[TestWith([0, 0, 1, 'ảnh-đẹp.png'])]
    #[TestWith([0, 1, 0, null])]
    public function test_saved_filename_modes_control_actual_upload_paths(int $original, int $uuid, int $noLatin, ?string $expected): void
    {
        $this->saveMedia(['media_use_original_name_for_file_path' => $original, 'media_convert_file_name_to_uuid' => $uuid, 'media_turn_off_automatic_url_translation_into_latin' => $noLatin]);
        $file = $this->upload(UploadedFile::fake()->image('Ảnh đẹp.png', 90, 70));

        if ($uuid) {
            $this->assertTrue(Str::isUuid(pathinfo($file->url, PATHINFO_FILENAME)));
        } else {
            $this->assertSame($expected, basename($file->url));
        }
        $this->assertSame('Ảnh đẹp', $file->name);
        Storage::disk('public')->assertExists($file->url);
    }

    #[TestWith([0])]
    #[TestWith([1])]
    public function test_original_quality_or_webp_resize_settings_control_actual_image_bytes(int $keepOriginal): void
    {
        $this->saveMedia(['media_keep_original_file_size_and_quality' => $keepOriginal, 'media_convert_image_to_webp' => 1, 'media_reduce_large_image_size' => 1, 'media_image_max_width' => 200, 'media_image_max_height' => 200]);
        $image = UploadedFile::fake()->image('resized.jpg', 800, 400);
        $original = $image->getContent();
        $file = $this->upload($image);
        $bytes = Storage::disk('public')->get($file->url);
        $decoded = ImageManager::gd()->read($bytes);

        $this->assertSame($keepOriginal ? [800, 400] : [200, 100], [$decoded->width(), $decoded->height()]);
        $this->assertSame($keepOriginal ? 'image/jpeg' : 'image/webp', $file->mime_type);
        if ($keepOriginal) {
            $this->assertSame($original, $bytes);
        } else {
            $this->assertNotSame($original, $bytes);
            $this->assertSame('webp', pathinfo($file->url, PATHINFO_EXTENSION));
        }
    }

    public function test_custom_path_placeholder_and_maximum_upload_limit_use_saved_values(): void
    {
        $this->saveMedia(['media_customize_upload_path' => 1, 'media_upload_path' => 'assets/acceptance', 'media_default_placeholder_image' => 'placeholder.png']);
        do_action('init');
        Storage::forgetDisk('public');
        $this->assertSame(public_path('assets/acceptance'), RvMedia::getUploadPath());
        $this->assertSame(public_path('assets/acceptance'), config('filesystems.disks.public.root'));
        $file = $this->upload(UploadedFile::fake()->image('custom.png', 90, 70));
        $this->assertFileExists(public_path('assets/acceptance/'.$file->url));
        $this->assertSame(url('/assets/acceptance/'.$file->url), RvMedia::url($file->url));
        Storage::disk('public')->put('placeholder.png', ImageManager::gd()->create(20, 20)->fill('#cccccc')->toPng()->toString());
        $this->assertSame(url('/assets/acceptance/placeholder.png'), RvMedia::getDefaultImage());
        $this->assertFileExists(RvMedia::getRealPath('placeholder.png'));
        $before = Storage::disk('public')->allFiles();
        $this->saveMedia(['max_upload_filesize' => 0.0001, 'media_customize_upload_path' => 1, 'media_upload_path' => 'assets/acceptance']);

        $this->postJson(route('media.picker-upload'), ['file' => UploadedFile::fake()->image('too-large.jpg', 800, 400), 'folder_id' => 0])->assertOk()->assertJsonPath('error', true);

        $this->assertDatabaseCount('media_files', 1);
        $this->assertSame($before, Storage::disk('public')->allFiles());
    }

    #[TestWith(['top-left', 100, 3, 4, 5, 6])]
    #[TestWith(['bottom-right', 100, 3, 4, 85, 70])]
    #[TestWith(['top-right', 100, 3, 4, 85, 6])]
    #[TestWith(['bottom-left', 100, 3, 4, 5, 70])]
    #[TestWith(['center', 100, 3, 4, 45, 41])]
    #[TestWith(['top-left', 50, 3, 4, 5, 6])]
    public function test_watermark_saved_folder_scope_position_offset_and_opacity_are_applied_to_uploaded_pixels(string $position, int $opacity, int $offsetX, int $offsetY, int $sampleX, int $sampleY): void
    {
        $parent = MediaFolder::query()->create(['name' => 'Allowed', 'slug' => 'allowed', 'parent_id' => 0, 'user_id' => auth()->id()]);
        $child = MediaFolder::query()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->getKey(), 'user_id' => auth()->id()]);
        $excluded = MediaFolder::query()->create(['name' => 'Excluded', 'slug' => 'excluded', 'parent_id' => 0, 'user_id' => auth()->id()]);
        $imageManager = ImageManager::gd();
        Storage::disk('public')->put('watermark.png', $imageManager->create(20, 10)->fill('#ff0000')->toPng()->toString());
        $source = $imageManager->create(100, 80)->fill('#0000ff')->toPng()->toString();
        $this->saveMedia(['media_watermark_enabled' => 1, 'media_watermark_source' => 'watermark.png', 'media_watermark_size' => 20, 'media_watermark_opacity' => $opacity, 'media_watermark_position' => $position, 'media_watermark_position_x' => $offsetX, 'media_watermark_position_y' => $offsetY, 'media_folders_can_add_watermark_all' => 0, 'media_folders_can_add_watermark' => [(string) $parent->getKey()]]);
        foreach ([$parent, $child, $excluded] as $folder) {
            $file = $this->upload(UploadedFile::fake()->createWithContent('pixels.png', $source), $folder->getKey());
            $decoded = $imageManager->read(Storage::disk('public')->get($file->url));
            $color = $decoded->pickColor($sampleX, $sampleY)->toHex();
            if ($folder->is($excluded)) {
                $this->assertSame('0000ff', $color);
            } elseif ($opacity === 100) {
                $this->assertSame('ff0000', $color);
            } else {
                $this->assertNotSame('ff0000', $color);
                $this->assertNotSame('0000ff', $color);
                $this->assertEqualsWithDelta(127.5, hexdec(substr($color, 0, 2)), 1);
                $this->assertEqualsWithDelta(127.5, hexdec(substr($color, 4, 2)), 1);
            }
            $this->assertSame('0000ff', $decoded->pickColor(0, 0)->toHex());
            if (! $folder->is($excluded) && $position === 'top-left' && $opacity === 100) {
                $this->assertSame('ff0000', $decoded->pickColor(22, 13)->toHex());
                $this->assertSame('0000ff', $decoded->pickColor(23, 13)->toHex());
                $this->assertSame('0000ff', $decoded->pickColor(22, 14)->toHex());
            }
        }
    }
}
