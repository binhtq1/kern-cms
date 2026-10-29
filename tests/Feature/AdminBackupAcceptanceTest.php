<?php

namespace Tests\Feature;

use Composer\Autoload\ClassLoader;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kern\Backup\Providers\BackupServiceProvider;
use Kern\Backup\Supports\Backup;
use Tests\TestCase;
use ZipArchive;

class AdminBackupAcceptanceTest extends TestCase
{
    private ?string $temporaryRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('ADMIN_BACKUP_ACCEPTANCE_MYSQL') !== '1') {
            $this->markTestSkipped('Requires the disposable admin acceptance MySQL database.');
        }

        $this->assertSame('mysql', DB::getConfig('driver'));
        $this->assertSame('acceptance-mysql', DB::getConfig('host'));
        $this->assertSame('kern_acceptance_tests', DB::getDatabaseName());
        $this->assertSame('kern_acceptance', DB::getConfig('username'));

        $this->temporaryRoot = sys_get_temp_dir().'/kern-backup-acceptance-'.Str::uuid();
        $this->app->useStoragePath($this->temporaryRoot.'/storage');
        $this->app->useBootstrapPath($this->temporaryRoot.'/bootstrap');
        foreach (['uploads', 'views', 'purifier', 'debugbar', 'storage/logs', 'bootstrap/cache'] as $directory) {
            File::ensureDirectoryExists($this->temporaryRoot.'/'.$directory);
        }
        config([
            'packages.installer.installer.enabled' => false,
            'core.base.admin.legacy_mode' => false,
            'filesystems.disks.public.root' => $this->temporaryRoot.'/uploads',
            'view.compiled' => $this->temporaryRoot.'/views',
            'purifier.cachePath' => $this->temporaryRoot.'/purifier',
            'debugbar.storage.path' => $this->temporaryRoot.'/debugbar',
            'logging.channels.single.path' => $this->temporaryRoot.'/storage/logs/laravel.log',
            'logging.default' => 'single',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        $this->withoutVite();
        $loader = new ClassLoader;
        $loader->setPsr4('Kern\\Backup\\', plugin_path('backup/src'));
        $loader->register(true);
        $this->app->register(BackupServiceProvider::class);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->artisan('migrate:fresh', ['--force' => true, '--drop-views' => true])->assertSuccessful();
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        if ($this->temporaryRoot !== null) {
            File::deleteDirectory($this->temporaryRoot);
        }

        parent::tearDown();
    }

    public function test_real_mysql_and_uploads_backup_download_restore_and_delete(): void
    {
        $this->freezeTime();
        DB::table('settings')->insert(['key' => 'acceptance_backup_fixture', 'value' => 'original database value']);
        File::put($this->temporaryRoot.'/uploads/fixture.txt', 'original upload bytes');

        $this->postJson(route('backups.create'), ['name' => 'Acceptance snapshot', 'description' => 'Disposable fixture'])
            ->assertOk()->assertJsonPath('error', false);

        $backup = app(Backup::class);
        $key = array_key_first($backup->getBackupList());
        $this->assertNotNull($key);
        $databaseArchive = $backup->getBackupDatabasePath($key);
        $uploadArchive = $backup->getBackupPath($key.'/storage-'.$key.'.zip');
        $this->assertFileExists($databaseArchive);
        $this->assertFileExists($uploadArchive);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($databaseArchive));
        $dump = $zip->getFromName('database-'.$key.'.sql');
        $this->assertIsString($dump);
        $this->assertStringContainsString('original database value', $dump);
        $zip->close();
        $this->get(route('backups.download.database', $key))->assertDownload(basename($databaseArchive));
        $this->get(route('backups.download.uploads.folder', $key))->assertDownload(basename($uploadArchive));

        DB::table('settings')->where('key', 'acceptance_backup_fixture')->update(['value' => 'changed database value']);
        File::put($this->temporaryRoot.'/uploads/fixture.txt', 'changed upload bytes');
        File::put($this->temporaryRoot.'/uploads/added-after-backup.txt', 'must disappear');
        $this->postJson(route('backups.restore', $key))->assertOk()->assertJsonPath('error', false);

        $this->assertSame('original database value', DB::table('settings')->where('key', 'acceptance_backup_fixture')->value('value'));
        $this->assertSame('original upload bytes', File::get($this->temporaryRoot.'/uploads/fixture.txt'));
        $this->assertFileDoesNotExist($this->temporaryRoot.'/uploads/added-after-backup.txt');
        $this->deleteJson(route('backups.destroy', $key))->assertOk()->assertJsonPath('error', false);
        $this->assertSame([], $backup->getBackupList());
        $this->assertDirectoryDoesNotExist($backup->getBackupPath($key));
    }

    public function test_database_dump_failure_does_not_report_success_or_leave_a_backup_entry(): void
    {
        DB::statement('CREATE TABLE acceptance_dump_source (id INT)');
        DB::statement('CREATE VIEW acceptance_broken_view AS SELECT id FROM acceptance_dump_source');
        DB::statement('DROP TABLE acceptance_dump_source');

        $this->postJson(route('backups.create'), ['name' => 'Failed snapshot'])
            ->assertOk()->assertJsonPath('error', true);
        $this->assertSame([], app(Backup::class)->getBackupList());
    }

    public function test_invalid_database_restore_reports_failure_before_removing_uploads(): void
    {
        $this->freezeTime();
        $backup = app(Backup::class);
        $key = $backup->createBackupFolder('Broken SQL')['key'];
        $sql = $backup->getBackupPath($key.'/database-'.$key.'.sql');
        File::put($sql, '-- '.str_repeat('invalid dump fixture ', 100)."\nTHIS IS INVALID SQL;\n");
        $backup->compressFileToZip($sql, $backup->getBackupDatabasePath($key));
        File::put($this->temporaryRoot.'/uploads/fixture.txt', 'keep current uploads');
        $backup->backupFolder($this->temporaryRoot.'/uploads');
        File::put($this->temporaryRoot.'/uploads/fixture.txt', 'live uploads must survive failed database restore');

        $this->postJson(route('backups.restore', $key))->assertOk()->assertJsonPath('error', true);
        $this->assertSame('live uploads must survive failed database restore', File::get($this->temporaryRoot.'/uploads/fixture.txt'));
    }

    public function test_unreadable_archive_is_not_reported_as_extracted(): void
    {
        $archive = $this->temporaryRoot.'/invalid.zip';
        File::put($archive, 'not a zip archive');
        $this->assertFalse(app(Backup::class)->extractFileTo($archive, $this->temporaryRoot.'/extracted'));
        $backup = app(Backup::class);
        $backup->createBackupFolder('Missing upload folder');
        $this->assertFalse($backup->backupFolder($this->temporaryRoot.'/missing-upload-directory'));
    }

    public function test_corrupted_upload_archive_does_not_delete_live_uploads(): void
    {
        $this->freezeTime();
        DB::table('settings')->insert(['key' => 'corrupt_uploads_fixture', 'value' => 'original database value']);
        File::put($this->temporaryRoot.'/uploads/fixture.txt', 'original bytes');
        $this->postJson(route('backups.create'), ['name' => 'Corrupted uploads'])->assertJsonPath('error', false);
        $backup = app(Backup::class);
        $key = array_key_first($backup->getBackupList());
        File::put($backup->getBackupPath($key.'/storage-'.$key.'.zip'), 'corrupt archive');
        DB::table('settings')->where('key', 'corrupt_uploads_fixture')->update(['value' => 'live database must survive']);
        File::put($this->temporaryRoot.'/uploads/fixture.txt', 'live upload must survive');

        $this->postJson(route('backups.restore', $key))->assertOk()->assertJsonPath('error', true);
        $this->assertSame('live database must survive', DB::table('settings')->where('key', 'corrupt_uploads_fixture')->value('value'));
        $this->assertFileExists($this->temporaryRoot.'/uploads/fixture.txt');
        $this->assertSame('live upload must survive', File::get($this->temporaryRoot.'/uploads/fixture.txt'));
    }

    public function test_backup_actions_reject_parent_directory_as_backup_key(): void
    {
        app(Backup::class)->createBackupFolder('Safe snapshot');
        $sentinel = $this->temporaryRoot.'/storage/app/protected-fixture.txt';
        File::put($sentinel, 'must survive');

        $this->deleteJson(route('backups.destroy', '..'))->assertNotFound();
        $this->postJson(route('backups.restore', '..'))->assertNotFound();
        $this->getJson(route('backups.download.database', '..'))->assertNotFound();
        $this->getJson(route('backups.download.uploads.folder', '..'))->assertNotFound();
        $this->deleteJson(route('backups.destroy', '2000-01-01-00-00-00'))->assertNotFound();
        $this->assertSame('must survive', File::get($sentinel));
    }

    public function test_user_without_backup_permissions_cannot_create_restore_download_or_delete(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->postJson(route('backups.create'), ['name' => 'Forbidden'])->assertUnauthorized();
        $this->postJson(route('backups.restore', 'missing'))->assertUnauthorized();
        $this->getJson(route('backups.download.database', 'missing'))->assertUnauthorized();
        $this->getJson(route('backups.download.uploads.folder', 'missing'))->assertUnauthorized();
        $this->deleteJson(route('backups.destroy', 'missing'))->assertUnauthorized();
        $this->assertSame([], app(Backup::class)->getBackupList());
    }
}
