<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Kern\Blog\Models\Category;
use Kern\Media\Models\MediaFile;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminOperationalActionsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);

        $this->temporaryRoot = sys_get_temp_dir().'/kern-admin-operations-'.bin2hex(random_bytes(8));
        $storageRoot = $this->temporaryRoot.'/storage';
        $bootstrapRoot = $this->temporaryRoot.'/bootstrap';
        foreach ([
            $storageRoot.'/framework/views',
            $storageRoot.'/logs',
            $storageRoot.'/public',
            $storageRoot.'/local',
            $bootstrapRoot.'/cache',
            $this->temporaryRoot.'/purifier',
            $this->temporaryRoot.'/debugbar/acceptance',
        ] as $directory) {
            File::ensureDirectoryExists($directory);
        }

        $this->app->useStoragePath($storageRoot);
        $this->app->useBootstrapPath($bootstrapRoot);
        config([
            'filesystems.default' => 'public',
            'filesystems.disks.public.root' => $storageRoot.'/public',
            'filesystems.disks.local.root' => $storageRoot.'/public',
            'view.compiled' => $storageRoot.'/framework/views',
            'purifier.cachePath' => $this->temporaryRoot.'/purifier',
            'debugbar.storage.path' => $this->temporaryRoot.'/debugbar',
            'core.base.admin.modules.settings' => 'inertia',
            'core.base.admin.modules.system' => 'inertia',
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');

        if (! is_plugin_active('blog')) {
            $this->assertFalse(app(PluginService::class)->activate('blog')['error']);
        }

        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    private function headers(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
            'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json'))
                ? hash_file('sha256', public_path('build/admin/manifest.json'))
                : '',
        ];
    }

    public function test_media_settings_generate_a_real_thumbnail_and_apply_watermark_to_the_source(): void
    {
        $imageManager = ImageManager::gd();
        $sourceBytes = $imageManager->create(240, 160)->fill('#123456')->toPng()->toString();
        $watermarkBytes = $imageManager->create(40, 20)->fill('#ef233c')->toPng()->toString();
        Storage::disk('public')->put('acceptance/source.png', $sourceBytes);
        Storage::disk('public')->put('acceptance/watermark.png', $watermarkBytes);
        setting()->forceSet([
            'media_enable_thumbnail_sizes' => '1',
            'media_sizes_thumb_width' => 60,
            'media_sizes_thumb_height' => 40,
            'media_watermark_enabled' => '1',
            'media_watermark_source' => 'acceptance/watermark.png',
            'media_watermark_size' => 30,
            'media_watermark_position' => 'center',
            'media_watermark_position_x' => 0,
            'media_watermark_position_y' => 0,
            'media_watermark_opacity' => 100,
            'media_folders_can_add_watermark' => '[]',
        ])->save();

        MediaFile::query()->create([
            'name' => 'acceptance-source.png',
            'mime_type' => 'image/png',
            'size' => strlen($sourceBytes),
            'url' => 'acceptance/source.png',
            'folder_id' => 0,
            'user_id' => auth()->id(),
        ]);

        $response = $this->postJson(route('settings.media.generate-thumbnails'), [
            'total' => 1,
            'offset' => 0,
            'limit' => 10,
        ])->assertOk()->assertJsonPath('error', false);

        $this->assertSame(10, $response->json('data.next'));
        Storage::disk('public')->assertExists('acceptance/source-60x40.png');
        $this->assertNotSame($sourceBytes, Storage::disk('public')->get('acceptance/source.png'), 'Watermark generation should update the source image.');
        $thumbnail = $imageManager->read(Storage::disk('public')->path('acceptance/source-60x40.png'));
        $this->assertSame(60, $thumbnail->width());
        $this->assertSame(40, $thumbnail->height());
    }

    public function test_thumbnail_generation_processes_successive_batches_without_changing_original_images(): void
    {
        config(['core.media.media.sizes' => ['thumb' => '60x40']]);
        setting()->forceSet(['media_enable_thumbnail_sizes' => '1', 'media_watermark_enabled' => '0'])->save();
        $manager = ImageManager::gd();
        $bytes = $manager->create(120, 80)->fill('#123456')->toPng()->toString();
        foreach (range(1, 11) as $index) {
            $path = 'acceptance/batch-'.$index.'.png';
            Storage::disk('public')->put($path, $bytes);
            MediaFile::query()->create(['name' => 'Batch '.$index, 'mime_type' => 'image/png', 'size' => strlen($bytes), 'url' => $path, 'folder_id' => 0, 'user_id' => auth()->id()]);
        }
        $this->postJson(route('settings.media.generate-thumbnails'), ['total' => 11, 'offset' => 0, 'limit' => 10])->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.total', 11)->assertJsonPath('data.next', 10);
        Storage::disk('public')->assertMissing('acceptance/batch-11-60x40.png');
        foreach (range(1, 10) as $index) {
            $thumbnail = $manager->read(Storage::disk('public')->path('acceptance/batch-'.$index.'-60x40.png'));
            $this->assertSame(60, $thumbnail->width());
            $this->assertSame(40, $thumbnail->height());
            $this->assertSame($bytes, Storage::disk('public')->get('acceptance/batch-'.$index.'.png'));
        }
        $this->postJson(route('settings.media.generate-thumbnails'), ['total' => 11, 'offset' => 10, 'limit' => 10])->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.total', 11)->assertJsonPath('data.next', 20);
        $thumbnail = $manager->read(Storage::disk('public')->path('acceptance/batch-11-60x40.png'));
        $this->assertSame(60, $thumbnail->width());
        $this->assertSame(40, $thumbnail->height());
        $this->assertSame($bytes, Storage::disk('public')->get('acceptance/batch-11.png'));
        $this->assertDatabaseCount('media_files', 11);
    }

    public function test_corrupt_thumbnail_source_reports_failure_and_keeps_processing_other_images(): void
    {
        config(['core.media.media.sizes' => ['thumb' => '60x40']]);
        setting()->forceSet(['media_enable_thumbnail_sizes' => '1', 'media_watermark_enabled' => '0'])->save();
        $manager = ImageManager::gd();
        $bytes = $manager->create(120, 80)->fill('#123456')->toPng()->toString();
        foreach (['before' => $bytes, 'corrupt' => 'Not a decodable image', 'after' => $bytes] as $name => $content) {
            $path = 'acceptance/'.$name.'.png';
            Storage::disk('public')->put($path, $content);
            MediaFile::query()->create(['name' => $name, 'mime_type' => 'image/png', 'size' => strlen($content), 'url' => $path, 'folder_id' => 0, 'user_id' => auth()->id()]);
        }
        $this->postJson(route('settings.media.generate-thumbnails'), ['total' => 3, 'offset' => 0, 'limit' => 10])->assertOk()->assertJsonPath('error', true)->assertJsonPath('message', trans('core/setting::setting.generate_thumbnails_error', ['count' => 1]));
        foreach (['before', 'after'] as $name) {
            $thumbnail = $manager->read(Storage::disk('public')->path('acceptance/'.$name.'-60x40.png'));
            $this->assertSame(60, $thumbnail->width());
            $this->assertSame(40, $thumbnail->height());
            $this->assertSame($bytes, Storage::disk('public')->get('acceptance/'.$name.'.png'));
        }
        Storage::disk('public')->assertMissing('acceptance/corrupt-60x40.png');
        $this->assertSame('Not a decodable image', Storage::disk('public')->get('acceptance/corrupt.png'));
        $this->assertDatabaseCount('media_files', 3);
    }

    public function test_cache_operations_clear_only_their_temporary_targets(): void
    {
        Cache::put('acceptance-cache-key', 'cached');
        File::put($this->temporaryRoot.'/storage/framework/views/acceptance-view.php', '<?php echo "view";');
        File::put($this->temporaryRoot.'/bootstrap/cache/routes-v7.php', '<?php return [];');
        File::put($this->temporaryRoot.'/bootstrap/cache/config.php', '<?php return [];');
        File::put($this->temporaryRoot.'/storage/logs/acceptance.log', 'acceptance log');
        File::put($this->temporaryRoot.'/purifier/acceptance.cache', 'purifier cache');
        File::ensureDirectoryExists($this->temporaryRoot.'/debugbar/acceptance');
        File::put($this->temporaryRoot.'/debugbar/acceptance/trace.json', '{}');
        File::ensureDirectoryExists($this->temporaryRoot.'/storage/public/fonts');
        File::put($this->temporaryRoot.'/storage/public/fonts/acceptance.css', 'font cache');
        config(['core.base.general.google_fonts_enabled_cache' => true]);

        $this->postJson(route('system.cache.clear'), ['type' => 'clear_cms_cache'])->assertOk();
        $this->postJson(route('system.cache.clear'), ['type' => 'refresh_compiled_views'])->assertOk();
        $this->postJson(route('system.cache.clear'), ['type' => 'clear_config_cache'])->assertOk();
        $this->postJson(route('system.cache.clear'), ['type' => 'clear_route_cache'])->assertOk();
        $this->postJson(route('system.cache.clear'), ['type' => 'clear_log'])->assertOk();

        $this->assertFalse(File::exists($this->temporaryRoot.'/storage/framework/views/acceptance-view.php'));
        $this->assertFalse(File::exists($this->temporaryRoot.'/bootstrap/cache/config.php'));
        $this->assertFalse(File::exists($this->temporaryRoot.'/bootstrap/cache/routes-v7.php'));
        $this->assertFalse(File::exists($this->temporaryRoot.'/storage/logs/acceptance.log'));
        $this->assertFalse(File::exists($this->temporaryRoot.'/purifier/acceptance.cache'));
        $this->assertFalse(File::exists($this->temporaryRoot.'/debugbar/acceptance'));
        $this->assertFalse(File::exists($this->temporaryRoot.'/storage/public/fonts/acceptance.css'));
        $this->assertNull(Cache::get('acceptance-cache-key'));
    }

    public function test_database_cleanup_preserves_protected_records_and_cleans_its_configured_media_root(): void
    {
        $this->assertSame($this->temporaryRoot.'/storage/public', rtrim(Storage::disk()->path(''), '/'));
        $user = auth()->user();
        $page = Page::query()->create([
            'name' => 'Protected acceptance page',
            'content' => '<p>Keep this page</p>',
            'status' => 'published',
            'template' => 'default',
            'user_id' => $user->getKey(),
        ]);
        $category = Category::query()->create([
            'author_type' => get_class($user),
            'author_id' => $user->getKey(),
            'name' => 'Cleanup acceptance category',
            'status' => 'published',
            'parent_id' => 0,
        ]);
        Storage::disk()->put('acceptance/cleanup-sentinel.txt', 'temporary media');
        MediaFile::query()->create([
            'name' => 'cleanup-sentinel.txt',
            'mime_type' => 'text/plain',
            'size' => strlen('temporary media'),
            'url' => 'acceptance/cleanup-sentinel.txt',
            'folder_id' => 0,
            'user_id' => $user->getKey(),
        ]);
        config(['core.base.general.enabled_cleanup_database' => true]);

        $this->postJson(route('system.cleanup.process'), ['tables' => []])->assertOk()->assertJsonPath('error', false);

        $this->assertDatabaseHas('pages', ['id' => $page->getKey(), 'name' => 'Protected acceptance page']);
        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
        $this->assertDatabaseMissing('categories', ['id' => $category->getKey()]);
        $this->assertDatabaseMissing('media_files', ['url' => 'acceptance/cleanup-sentinel.txt']);
        Storage::disk()->assertMissing('acceptance/cleanup-sentinel.txt');
    }
}
