<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Kern\Base\Services\CleanDatabaseService;
use Kern\Media\Models\MediaFile;
use League\Flysystem\UnableToDeleteDirectory;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminDatabaseCleanupAcceptanceTest extends TestCase
{
    use DatabaseMigrations;

    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryRoot = sys_get_temp_dir().'/kern-cleanup-'.bin2hex(random_bytes(8));
        foreach (['media', 'private'] as $directory) {
            File::ensureDirectoryExists($this->temporaryRoot.'/'.$directory);
        }
        config([
            'filesystems.default' => 'local',
            'core.media.media.disk' => 'public',
            'filesystems.disks.public.root' => $this->temporaryRoot.'/media',
            'filesystems.disks.local.root' => $this->temporaryRoot.'/private',
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);
        parent::tearDown();
    }

    private function exceptMediaTable(): array
    {
        return array_values(array_filter(DB::connection()->getSchemaBuilder()->getTableListing(), fn (string $table): bool => str($table)->afterLast('.')->toString() !== 'media_files'));
    }

    public function test_cleanup_uses_configured_media_disk_and_keeps_unrelated_default_disk_and_protected_records(): void
    {
        $user = UserFactory::new()->superUser()->create();
        MediaFile::query()->create(['name' => 'Cleanup file', 'url' => 'nested/file.txt', 'mime_type' => 'text/plain', 'size' => 5, 'folder_id' => 0, 'user_id' => $user->getKey()]);
        Storage::disk('public')->put('nested/file.txt', 'media');
        Storage::disk('public')->put('root.txt', 'media');
        Storage::disk('local')->put('private.txt', 'keep');

        $this->assertTrue(app(CleanDatabaseService::class)->execute($this->exceptMediaTable()));

        $this->assertDatabaseCount('media_files', 0);
        $this->assertModelExists($user);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('public')->directories());
        $this->assertSame('keep', Storage::disk('local')->get('private.txt'));
    }

    public function test_excluding_qualified_media_table_keeps_its_records_and_files(): void
    {
        $user = UserFactory::new()->superUser()->create();
        $file = MediaFile::query()->create(['name' => 'Keep file', 'url' => 'keep.txt', 'mime_type' => 'text/plain', 'size' => 4, 'folder_id' => 0, 'user_id' => $user->getKey()]);
        Storage::disk('public')->put('keep.txt', 'keep');

        $this->assertFalse(app(CleanDatabaseService::class)->execute(DB::connection()->getSchemaBuilder()->getTableListing()));

        $this->assertModelExists($file);
        $this->assertSame('keep', Storage::disk('public')->get('keep.txt'));
    }

    private function assertForeignKeysEnabled(): void
    {
        if (! Schema::hasTable('cleanup_constraint_parents')) {
            Schema::create('cleanup_constraint_parents', function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('cleanup_constraint_children', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->constrained('cleanup_constraint_parents');
            });
        }

        try {
            DB::transaction(fn () => DB::table('cleanup_constraint_children')->insert(['parent_id' => 999999]));
            $this->fail('Foreign keys must reject references to missing parents.');
        } catch (QueryException $exception) {
            $this->assertSame(DB::getDriverName() === 'pgsql' ? '23503' : '23000', $exception->errorInfo[0]);
        }
    }

    public function test_truncate_failure_restores_foreign_keys_and_keeps_media_files(): void
    {
        Storage::disk('public')->put('keep.txt', 'keep');
        $this->assertForeignKeysEnabled();
        DB::connection()->beforeExecuting(function (string $query): void {
            if (preg_match('/(?:delete\s+from|truncate\s+)/i', $query) && str_contains($query, 'media_files')) {
                throw new RuntimeException('Simulated truncate failure');
            }
        });

        try {
            app(CleanDatabaseService::class)->execute($this->exceptMediaTable());
            $this->fail('A failed truncate must not report success.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated truncate failure', $exception->getMessage());
        }

        $this->assertForeignKeysEnabled();
        $this->assertSame('keep', Storage::disk('public')->get('keep.txt'));
    }

    public function test_failed_media_directory_deletion_does_not_report_success_or_touch_default_disk(): void
    {
        Storage::disk('local')->put('private.txt', 'keep');
        $storage = Mockery::mock(FilesystemAdapter::class);
        $storage->shouldReceive('directories')->once()->andReturn(['blocked']);
        $storage->shouldReceive('deleteDirectory')->once()->with('blocked')->andReturn(false);
        Storage::set('public', $storage);

        try {
            app(CleanDatabaseService::class)->execute($this->exceptMediaTable());
            $this->fail('A failed media deletion must not report success.');
        } catch (UnableToDeleteDirectory $exception) {
            $this->assertStringContainsString('blocked', $exception->getMessage());
        }

        $this->assertSame('keep', Storage::disk('local')->get('private.txt'));
        $this->assertForeignKeysEnabled();
    }
}
