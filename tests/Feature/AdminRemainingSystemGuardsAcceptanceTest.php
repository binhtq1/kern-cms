<?php

namespace Tests\Feature;

use Composer\Autoload\ClassLoader;
use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Kern\Backup\Http\Controllers\BackupController;
use Kern\Backup\Providers\BackupServiceProvider;
use Kern\Backup\Supports\Backup;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminRemainingSystemGuardsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceRoot;

    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->sourceRoot = base_path();
        $this->fixtureRoot = $this->sourceRoot.'/tmp/acceptance-system-'.bin2hex(random_bytes(8));
        foreach (['platform/plugins', 'public/storage', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'bootstrap/cache', 'lang', 'resources', 'config'] as $directory) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$directory);
        }
        File::copyDirectory(public_path('build'), $this->fixtureRoot.'/public/build');
        File::copy($this->sourceRoot.'/composer.json', $this->fixtureRoot.'/composer.json');
        $this->app->setBasePath($this->fixtureRoot);
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->useBootstrapPath($this->fixtureRoot.'/bootstrap');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config([
            'packages.installer.installer.enabled' => false,
            'core.base.admin.legacy_mode' => false,
            'core.base.general.enabled_cleanup_database' => true,
            'filesystems.default' => 'public',
            'core.media.media.disk' => 'public',
            'filesystems.disks.public.root' => $this->fixtureRoot.'/public/storage',
            'filesystems.disks.local.root' => $this->fixtureRoot.'/storage/private',
            'view.compiled' => $this->fixtureRoot.'/storage/framework/views',
            'purifier.cachePath' => $this->fixtureRoot.'/storage/purifier',
            'logging.channels.single.path' => $this->fixtureRoot.'/storage/logs/laravel.log',
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');
        File::copyDirectory($this->sourceRoot.'/platform/plugins/backup', plugin_path('backup'));
        $loader = new ClassLoader;
        $loader->setPsr4('Kern\\Backup\\', plugin_path('backup/src'));
        $loader->register(true);
        $this->app->register(BackupServiceProvider::class);
        $this->app['router']->getRoutes()->refreshNameLookups();
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

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_enabled_cleanup_rejects_restricted_users_or_demo_without_erasing_records_or_files(bool $demo): void
    {
        $page = PageFactory::new()->create();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 1, 'created_at' => 1]);
        Storage::disk('public')->put('guard.txt', 'keep uploads');
        if ($demo) {
            config(['core.base.general.demo_mode_enabled' => true]);
        } else {
            $this->actingAs(UserFactory::new()->create(['permissions' => ['system.index' => true]]));
        }

        $response = $this->postJson(route('system.cleanup.process'), ['tables' => []])->assertStatus($demo ? 403 : 401);
        if ($demo) {
            $response->assertJsonPath('error', true);
        }

        $this->assertModelExists($page);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('keep uploads', Storage::disk('public')->get('guard.txt'));
    }

    public function test_enabled_cleanup_exposes_the_database_and_files_backup_warning(): void
    {
        $this->get(route('system.cleanup'), $this->headers())->assertOk()->assertJsonPath('component', 'System/Cleanup')->assertJsonPath('props.enabled', true)->assertJsonPath('props.warning', 'Please backup your database and script files before cleanup, it will clear your data in database.');
    }

    #[TestWith(['create'])]
    #[TestWith(['restore'])]
    #[TestWith(['destroy'])]
    public function test_backup_demo_actions_leave_database_uploads_archives_and_inventory_untouched(string $action): void
    {
        $page = PageFactory::new()->create();
        $backup = app(Backup::class);
        $key = $backup->createBackupFolder('Guard snapshot')['key'];
        $archive = $backup->getBackupDatabasePath($key);
        File::put($archive, 'unchanged archive bytes');
        Storage::disk('public')->put('guard.txt', 'keep uploads');
        $inventory = $backup->getBackupList();
        $settings = DB::table('settings')->orderBy('key')->get()->toJson();
        config(['core.base.general.demo_mode_enabled' => true]);

        (match ($action) {
            'create' => $this->postJson(route('backups.create'), ['name' => 'Denied snapshot']),
            'restore' => $this->postJson(route('backups.restore', $key)),
            'destroy' => $this->deleteJson(route('backups.destroy', $key)),
        })->assertForbidden()->assertJsonPath('error', true);

        $this->assertModelExists($page);
        $this->assertSame($settings, DB::table('settings')->orderBy('key')->get()->toJson());
        $this->assertSame($inventory, $backup->getBackupList());
        $this->assertSame('unchanged archive bytes', File::get($archive));
        $this->assertSame('keep uploads', Storage::disk('public')->get('guard.txt'));
        $this->assertCount(1, File::directories($backup->getBackupPath()));
    }

    #[TestWith(['mysql', ['important_message1', 'important_message2', 'important_message3', 'important_message4']])]
    #[TestWith(['pgsql', ['important_message_pgsql1', 'important_message_pgsql2']])]
    public function test_backup_presenter_retains_driver_specific_blade_guidance_without_exposing_html(string $driver, array $keys): void
    {
        $databaseDriver = DB::getConfig('driver');
        $controller = app(BackupController::class);
        (new \ReflectionProperty($controller, 'databaseDriver'))->setValue($controller, $driver);
        $this->app->instance(BackupController::class, $controller);

        $response = $this->get(route('backups.index'), $this->headers())->assertOk();

        $this->assertSame(array_map(fn (string $key): string => strip_tags(trans('plugins/backup::backup.'.$key)), $keys), $response->json('props.warnings'));
        $this->assertSame($databaseDriver, DB::getConfig('driver'));
        foreach ($response->json('props.warnings') as $warning) {
            $this->assertSame(strip_tags($warning), $warning);
        }
    }

    public function test_disabled_proc_open_backup_warning_is_preserved_from_blade_in_react(): void
    {
        if (getenv('ACCEPTANCE_PROC_OPEN_CHILD') !== '1') {
            $process = new Process([PHP_BINARY, '-d', 'disable_functions=proc_open', $this->sourceRoot.'/vendor/bin/phpunit', '--do-not-cache-result', '--filter', __FUNCTION__, $this->sourceRoot.'/tests/Feature/AdminRemainingSystemGuardsAcceptanceTest.php'], $this->sourceRoot, ['ACCEPTANCE_PROC_OPEN_CHILD' => '1', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array'], timeout: 30);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());

            return;
        }

        $this->assertFalse(function_exists('proc_open'));
        config(['core.base.admin.legacy_mode' => true]);
        $this->get(route('backups.index'))->assertOk()->assertSee('proc_open()', false);
        config(['core.base.admin.legacy_mode' => false]);

        $response = $this->get(route('backups.index'), $this->headers())->assertOk()->assertJsonPath('component', 'System/Console');

        $this->assertStringContainsString('proc_open()', json_encode(collect($response->json('props'))->except(['admin', 'errors', 'flash'])->all(), JSON_THROW_ON_ERROR));
    }
}
