<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Kern\Base\Supports\Upgrade\DatabaseSnapshot;
use Kern\Base\Supports\Upgrade\NativeFrontendCompatibility;
use Kern\Base\Supports\Upgrade\RuntimeAssets;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CmsUpgradeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kern-upgrade-'.bin2hex(random_bytes(8));
        foreach (['database', 'bootstrap/cache', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'public', 'runtime'] as $path) {
            File::ensureDirectoryExists($this->directory.'/'.$path);
        }
        File::copyDirectory(database_path('migrations'), $this->directory.'/database/migrations');
        $this->app->useStoragePath($this->directory.'/storage');
        $this->app->useEnvironmentPath($this->directory);
        $this->app->useBootstrapPath($this->directory.'/bootstrap');
        $this->app->useDatabasePath($this->directory.'/database');
        $this->app->usePublicPath($this->directory.'/public');
        File::put($this->directory.'/.env', 'APP_KEY='.config('app.key')."\nSITE_CUSTOM=retained\n");
        File::put($this->directory.'/database/database.sqlite', '');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->directory.'/database/database.sqlite', 'view.compiled' => $this->directory.'/storage/framework/views']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        File::put(storage_path('installed'), 'installed-0.1.0');
        DB::table('settings')->insert(['key' => 'site_custom', 'value' => 'original']);
        File::put($this->directory.'/runtime/manifest.json', '{"entry":{"file":"new.js"}}');
        File::put($this->directory.'/runtime/new.js', 'new runtime');
        $this->app->instance(RuntimeAssets::class, $this->runtimeAssets());
    }

    private function runtimeAssets(?Filesystem $files = null): RuntimeAssets
    {
        return new class($files ?? new Filesystem, $this->directory.'/runtime', public_path('build/admin'), $this->directory.'/stock-assets.json') extends RuntimeAssets
        {
            public function __construct(Filesystem $files, private string $source, private string $destination, private string $catalog)
            {
                parent::__construct($files);
            }

            public function sources(): array
            {
                return [$this->source => $this->destination];
            }

            protected function stockManifestPath(): string
            {
                return $this->catalog;
            }
        };
    }

    private function stockAsset(string $relative, string $content): void
    {
        File::ensureDirectoryExists(dirname(public_path($relative)));
        File::put(public_path($relative), $content);
        $path = $this->directory.'/stock-assets.json';
        $catalog = is_file($path) ? json_decode(File::get($path), true) : ['format' => 1, 'releases' => []];
        $catalog['releases']['0.3.2'][$relative] = hash('sha256', $content);
        File::put($path, json_encode($catalog, JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_upgrade_snapshots_data_applies_pending_migrations_and_preserves_customizations_on_retry(): void
    {
        $env = File::get($this->directory.'/.env');
        File::ensureDirectoryExists(public_path('themes/my-site'));
        File::put(public_path('themes/my-site/custom.css'), 'custom css');
        File::put($this->directory.'/database/migrations/2026_10_28_000001_add_upgrade_probe.php', '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { \\Illuminate\\Support\\Facades\\Schema::create("upgrade_probe", fn ($table) => $table->id()); } };');
        $this->artisan('cms:upgrade')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('upgrade_probe'));
        $migrations = DB::table('migrations')->count();
        $state = json_decode(File::get(storage_path('app/kern/upgrade.json')), true);
        $backup = new \PDO('sqlite:'.$state['snapshot'].'/database.sqlite');
        $this->assertSame('original', $backup->query("SELECT value FROM settings WHERE key = 'site_custom'")->fetchColumn());
        $this->assertFalse((bool) $backup->query("SELECT name FROM sqlite_master WHERE name = 'upgrade_probe'")->fetchColumn());
        $this->artisan('cms:upgrade')->assertSuccessful();
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertDatabaseHas('settings', ['key' => 'site_custom', 'value' => 'original']);
        $this->assertSame($env, File::get($this->directory.'/.env'));
        $this->assertSame('custom css', File::get(public_path('themes/my-site/custom.css')));
        $this->assertSame('installed-0.1.0', File::get(storage_path('installed')));
        $this->assertSame('0.2.0', $state['version']);
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_check_does_not_migrate_publish_or_create_a_snapshot(): void
    {
        $this->artisan('cms:upgrade', ['--check' => true])->assertSuccessful();
        $this->assertFileDoesNotExist(public_path('build/admin/manifest.json'));
        $this->assertDirectoryDoesNotExist(storage_path('kern-upgrades'));
        $this->assertFileDoesNotExist(storage_path('app/kern/upgrade.json'));
    }

    public function test_published_032_admin_manifest_is_recognized_offline_without_a_registry_and_backed_up_before_replacement(): void
    {
        Http::preventStrayRequests();
        $original = File::get(base_path('tests/fixtures/admin-manifest-0.3.2.json'));
        File::ensureDirectoryExists(public_path('build/admin'));
        File::put(public_path('build/admin/manifest.json'), $original);
        File::put(public_path('build/admin/site-custom.css'), 'customer styles');
        File::copy(core_path('base/resources/upgrade/stock-assets.json'), $this->directory.'/stock-assets.json');
        $this->artisan('cms:upgrade', ['--check' => true])->assertSuccessful();
        $this->assertSame($original, File::get(public_path('build/admin/manifest.json')));
        $this->assertFileDoesNotExist(storage_path('app/kern/runtime-assets.json'));
        $this->assertDirectoryDoesNotExist(storage_path('kern-upgrades'));

        $this->artisan('cms:upgrade')->assertSuccessful();
        $state = json_decode(File::get(storage_path('app/kern/upgrade.json')), true);
        $backup = $state['snapshot'];
        $this->assertSame($original, File::get($backup.'/assets/build/admin/manifest.json'));
        $inventory = json_decode(File::get($backup.'/asset-backup.json'), true);
        $this->assertSame(hash('sha256', $original), $inventory['assets']['build/admin/manifest.json']);
        $this->assertNull($inventory['assets']['build/admin/new.js']);
        $this->assertFalse($inventory['registry_existed']);
        $this->assertSame(File::get($this->directory.'/runtime/manifest.json'), File::get(public_path('build/admin/manifest.json')));
        $this->assertSame('completed', json_decode(File::get($backup.'/status.json'), true)['status']);
        $this->assertSame(0600, fileperms($backup.'/assets/build/admin/manifest.json') & 0777);
        $this->assertSame(0700, fileperms($backup) & 0777);
        $this->assertSame('customer styles', File::get(public_path('build/admin/site-custom.css')));
        $this->assertArrayNotHasKey('build/admin/site-custom.css', $inventory['assets']);
        $this->assertDatabaseHas('settings', ['key' => 'site_custom', 'value' => 'original']);
    }

    public function test_modified_published_manifest_is_rejected_without_migrations_or_an_asset_write(): void
    {
        $original = File::get(base_path('tests/fixtures/admin-manifest-0.3.2.json'));
        File::ensureDirectoryExists(public_path('build/admin'));
        File::put(public_path('build/admin/manifest.json'), $original."\n");
        File::copy(core_path('base/resources/upgrade/stock-assets.json'), $this->directory.'/stock-assets.json');
        File::ensureDirectoryExists(storage_path('app/kern'));
        File::put(storage_path('app/kern/runtime-assets.json'), json_encode(['build/admin/manifest.json' => hash('sha256', $original)]));
        $registry = File::get(storage_path('app/kern/runtime-assets.json'));
        $migrations = DB::table('migrations')->count();
        $this->artisan('cms:upgrade')->expectsOutputToContain('Customized CMS asset: build/admin/manifest.json')->assertFailed();
        $this->assertSame($original."\n", File::get(public_path('build/admin/manifest.json')));
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertDirectoryDoesNotExist(storage_path('kern-upgrades'));
        $this->assertSame($registry, File::get(storage_path('app/kern/runtime-assets.json')));
    }

    public function test_backup_preserves_previous_registry_and_original_asset_bytes_before_migrations_run(): void
    {
        $this->stockAsset('build/admin/new.js', 'old runtime');
        File::ensureDirectoryExists(storage_path('app/kern'));
        $registry = json_encode(['build/admin/new.js' => hash('sha256', 'old runtime')]);
        File::put(storage_path('app/kern/runtime-assets.json'), $registry);
        File::delete($this->directory.'/stock-assets.json');
        File::put($this->directory.'/database/migrations/2026_10_28_000002_assert_asset_backup.php', <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        $state = json_decode(file_get_contents(storage_path('app/kern/upgrade.json')), true);
        if (file_get_contents($state['snapshot'].'/assets/build/admin/new.js') !== 'old runtime') {
            throw new \RuntimeException('The asset backup must exist before migration');
        }
    }
};
PHP);
        $this->artisan('cms:upgrade')->assertSuccessful();
        $state = json_decode(File::get(storage_path('app/kern/upgrade.json')), true);
        $this->assertSame('old runtime', File::get($state['snapshot'].'/assets/build/admin/new.js'));
        $this->assertSame($registry, File::get($state['snapshot'].'/runtime-assets.json'));
        $this->assertSame(0600, fileperms($state['snapshot'].'/runtime-assets.json') & 0777);
        $this->assertTrue(json_decode(File::get($state['snapshot'].'/asset-backup.json'), true)['registry_existed']);
        $this->assertSame('new runtime', File::get(public_path('build/admin/new.js')));
        $this->assertSame(hash('sha256', 'new runtime'), json_decode(File::get(storage_path('app/kern/runtime-assets.json')), true)['build/admin/new.js']);
    }

    public function test_asset_backup_failure_stops_before_migration_or_publishing(): void
    {
        $this->stockAsset('build/admin/new.js', 'old runtime');
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('copy')->once()->andReturn(false);
        $this->app->instance(RuntimeAssets::class, $this->runtimeAssets($files));
        $migrations = DB::table('migrations')->count();
        $this->artisan('cms:upgrade')->expectsOutputToContain('Cannot back up CMS asset')->assertFailed();
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertSame('old runtime', File::get(public_path('build/admin/new.js')));
        $this->assertFileDoesNotExist(public_path('build/admin/manifest.json'));
        $this->assertFileDoesNotExist(storage_path('app/kern/runtime-assets.json'));
        $this->assertTrue(app()->isDownForMaintenance());
        $this->app->instance(RuntimeAssets::class, $this->runtimeAssets());
        $this->artisan('cms:upgrade')->assertSuccessful();
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_an_asset_changed_during_migrations_is_not_overwritten(): void
    {
        $this->stockAsset('build/admin/new.js', 'old runtime');
        File::put($this->directory.'/database/migrations/2026_10_28_000003_edit_asset.php', '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { file_put_contents(public_path("build/admin/new.js"), "concurrent customization"); } };');
        $this->artisan('cms:upgrade')->expectsOutputToContain('CMS asset changed during the upgrade: build/admin/new.js')->assertFailed();
        $this->assertSame('concurrent customization', File::get(public_path('build/admin/new.js')));
        $this->assertFileDoesNotExist(public_path('build/admin/manifest.json'));
        $this->assertTrue(app()->isDownForMaintenance());
    }

    public function test_invalid_stock_fingerprints_and_asset_symlinks_remain_protected(): void
    {
        $this->stockAsset('build/admin/new.js', 'old runtime');
        File::put($this->directory.'/stock-assets.json', '{"format":1,"releases":{"0.3.2":{"build/admin/new.js":"invalid"}}}');
        $this->artisan('cms:upgrade')->expectsOutputToContain('Invalid stock runtime asset fingerprint')->assertFailed();
        File::delete(public_path('build/admin/new.js'));
        File::put($this->directory.'/outside.js', 'outside data');
        symlink($this->directory.'/outside.js', public_path('build/admin/new.js'));
        $this->artisan('cms:upgrade')->expectsOutputToContain('Refusing to overwrite a symlink')->assertFailed();
        $this->assertSame('outside data', File::get($this->directory.'/outside.js'));
        $this->assertDirectoryDoesNotExist(storage_path('kern-upgrades'));
    }

    public function test_asset_parent_replaced_with_a_symlink_during_migration_is_rejected(): void
    {
        $this->stockAsset('build/admin/new.js', 'old runtime');
        File::put($this->directory.'/database/migrations/2026_10_28_000004_link_asset_parent.php', <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        rename(public_path('build/admin'), public_path('build/original-admin'));
        symlink(public_path('build/original-admin'), public_path('build/admin'));
    }
};
PHP);
        $this->artisan('cms:upgrade')->expectsOutputToContain('Refusing to publish through a symlink')->assertFailed();
        $this->assertSame('old runtime', File::get(public_path('build/original-admin/new.js')));
        $this->assertFileDoesNotExist(public_path('build/original-admin/manifest.json'));
        $this->assertTrue(app()->isDownForMaintenance());
    }

    public function test_legacy_site_owned_frontend_stops_upgrade_before_maintenance_or_data_changes(): void
    {
        $this->app->instance(NativeFrontendCompatibility::class, new class extends NativeFrontendCompatibility
        {
            public function assertSupported(): void
            {
                throw new RuntimeException('Site-owned frontend code still requires jQuery: plugin customer-contact.');
            }
        });
        $migrations = DB::table('migrations')->count();
        $this->artisan('cms:upgrade')->expectsOutputToContain('Site-owned frontend code still requires jQuery')->assertFailed();
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertDatabaseHas('settings', ['key' => 'site_custom', 'value' => 'original']);
        $this->assertDirectoryDoesNotExist(storage_path('kern-upgrades'));
        $this->assertFileDoesNotExist(storage_path('app/kern/upgrade.json'));
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_customized_runtime_assets_are_rejected_before_database_changes(): void
    {
        File::ensureDirectoryExists(public_path('build/admin'));
        File::put(public_path('build/admin/new.js'), 'site customization');
        $this->artisan('cms:upgrade')->expectsOutputToContain('Customized CMS asset')->assertFailed();
        $this->assertSame('site customization', File::get(public_path('build/admin/new.js')));
        $this->assertDirectoryDoesNotExist(storage_path('kern-upgrades'));
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_failed_backup_stops_migrations_and_a_retry_leaves_owned_maintenance_mode(): void
    {
        $this->app->instance(DatabaseSnapshot::class, new class extends DatabaseSnapshot
        {
            public function create(string $directory): string
            {
                throw new RuntimeException('Backup unavailable');
            }
        });
        $this->artisan('cms:upgrade')->expectsOutputToContain('Backup unavailable')->assertFailed();
        $this->assertTrue(app()->isDownForMaintenance());
        $this->assertFileDoesNotExist(public_path('build/admin/manifest.json'));
        $this->app->forgetInstance(DatabaseSnapshot::class);
        $this->artisan('cms:upgrade')->assertSuccessful();
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_failed_migrations_can_be_fixed_and_retried_without_losing_data(): void
    {
        $path = $this->directory.'/database/migrations/2026_10_28_000001_failed_upgrade_probe.php';
        File::put($path, '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { throw new \\RuntimeException("Migration interrupted"); } };');
        $this->artisan('cms:upgrade')->expectsOutputToContain('Migration interrupted')->assertFailed();
        $this->assertTrue(app()->isDownForMaintenance());
        $this->assertDatabaseHas('settings', ['key' => 'site_custom', 'value' => 'original']);
        $this->assertFileDoesNotExist(public_path('build/admin/manifest.json'));
        File::delete($path);
        $this->artisan('cms:upgrade')->assertSuccessful();
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_an_existing_maintenance_window_is_preserved(): void
    {
        $this->artisan('down')->assertSuccessful();
        $this->artisan('cms:upgrade')->assertSuccessful();
        $this->assertTrue(app()->isDownForMaintenance());
    }

    public function test_a_concurrent_upgrade_is_rejected(): void
    {
        $lock = fopen(storage_path('framework/kern-upgrade.lock'), 'c');
        flock($lock, LOCK_EX);
        try {
            $this->artisan('cms:upgrade')->expectsOutputToContain('Another CMS upgrade')->assertFailed();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_new_sites_and_downgrades_are_rejected(): void
    {
        File::delete(storage_path('installed'));
        $this->artisan('cms:upgrade')->assertFailed();
        File::put(storage_path('installed'), 'installed');
        File::ensureDirectoryExists(storage_path('app/kern'));
        File::put(storage_path('app/kern/upgrade.json'), '{"version":"99.0.0"}');
        $this->artisan('cms:upgrade')->expectsOutputToContain('Downgrading')->assertFailed();
    }
}
