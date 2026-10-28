<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Kern\Base\Supports\Upgrade\DatabaseSnapshot;
use Kern\Base\Supports\Upgrade\RuntimeAssets;
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
        $source = $this->directory.'/runtime';
        $destination = public_path('build/admin');
        $this->app->instance(RuntimeAssets::class, new class(new Filesystem, $source, $destination) extends RuntimeAssets
        {
            public function __construct(Filesystem $files, private string $source, private string $destination)
            {
                parent::__construct($files);
            }

            public function sources(): array
            {
                return [$this->source => $this->destination];
            }
        });
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
