<?php

namespace Tests\Unit;

use KernUpgrade\Upgrader;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class StandaloneUpgraderTest extends TestCase
{
    private string $directory;

    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../../kern-upgrade.php';
        $this->directory = sys_get_temp_dir().'/kern-standalone-test-'.bin2hex(random_bytes(8));
        $this->project = $this->directory.'/site';
        mkdir($this->project, 0700, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function write(string $path, string $contents): void
    {
        $path = $this->project.'/'.$path;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $contents);
    }

    private function archive(string $name, array $files): array
    {
        $path = $this->directory.'/'.$name;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $relative => $contents) {
            $zip->addFromString($relative, $contents);
        }
        $zip->close();

        return Upgrader::archive($path);
    }

    private function fingerprint(): array
    {
        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $hashes[substr($file->getPathname(), strlen($this->project) + 1)] = hash_file('sha256', $file->getPathname());
        }
        ksort($hashes);

        return $hashes;
    }

    public function test_updates_only_stock_files_and_preserves_site_code_data_and_unchanged_customizations(): void
    {
        $old = ['platform/plugins/contact/a.js' => 'old', 'platform/themes/site/view.php' => 'stock', 'public/themes/site/retired.js' => 'retired', 'public/vendor/core/core/base/libraries/jquery.min.js' => 'old library', 'public/build/admin/manifest.json' => 'old manifest'];
        $new = ['platform/plugins/contact/a.js' => 'native', 'platform/themes/site/view.php' => 'stock', 'platform/plugins/member/new.tsx' => 'react', 'public/build/admin/manifest.json' => 'new manifest'];
        foreach ([...$old, '.env' => 'APP_KEY=keep', 'storage/app/public/photo.jpg' => 'upload', 'app/Customer.php' => 'custom', 'platform/plugins/customer/code.php' => 'custom plugin'] as $path => $contents) {
            $this->write($path, $contents);
        }
        $this->write('platform/themes/site/view.php', 'customized theme');
        $baseline = $this->archive('old.zip', $old);
        $candidate = $this->archive('new.zip', $new);
        $plan = Upgrader::plan($this->project, $baseline, $candidate);
        $this->assertCount(5, $plan);
        Upgrader::apply($this->project, $plan, $candidate);
        $this->assertSame('native', file_get_contents($this->project.'/platform/plugins/contact/a.js'));
        $this->assertSame('react', file_get_contents($this->project.'/platform/plugins/member/new.tsx'));
        $this->assertSame('customized theme', file_get_contents($this->project.'/platform/themes/site/view.php'));
        $this->assertSame('APP_KEY=keep', file_get_contents($this->project.'/.env'));
        $this->assertSame('upload', file_get_contents($this->project.'/storage/app/public/photo.jpg'));
        $this->assertSame('custom plugin', file_get_contents($this->project.'/platform/plugins/customer/code.php'));
        $this->assertFileDoesNotExist($this->project.'/public/themes/site/retired.js');
        $this->assertFileDoesNotExist($this->project.'/public/vendor/core/core/base/libraries/jquery.min.js');
        $this->assertSame('new manifest', file_get_contents($this->project.'/public/build/admin/manifest.json'));
        $this->assertSame(0755, fileperms($this->project.'/platform/plugins/member') & 0777);
        $this->assertSame([], Upgrader::plan($this->project, $baseline, $candidate));
    }

    public function test_customized_changed_or_deleted_files_and_new_file_collisions_block_the_entire_plan(): void
    {
        $baseline = $this->archive('old.zip', ['platform/plugins/contact/a.js' => 'old', 'public/themes/site/retired.js' => 'old']);
        $candidate = $this->archive('new.zip', ['platform/plugins/contact/a.js' => 'new', 'platform/plugins/member/new.tsx' => 'new']);
        foreach (['platform/plugins/contact/a.js', 'public/themes/site/retired.js', 'platform/plugins/member/new.tsx'] as $path) {
            $this->write($path, 'customer code');
        }
        $before = $this->fingerprint();
        try {
            Upgrader::plan($this->project, $baseline, $candidate);
            $this->fail('The conflicting update must be rejected.');
        } catch (RuntimeException $error) {
            foreach (array_keys($before) as $path) {
                $this->assertStringContainsString($path, $error->getMessage());
            }
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_refuses_symlink_parents_and_detects_edits_after_preflight_before_any_write(): void
    {
        $baseline = $this->archive('old.zip', ['platform/plugins/a.js' => 'old', 'platform/plugins/b.js' => 'old']);
        $candidate = $this->archive('new.zip', ['platform/plugins/a.js' => 'new', 'platform/plugins/b.js' => 'new']);
        $this->write('platform/plugins/a.js', 'old');
        $this->write('platform/plugins/b.js', 'old');
        $plan = Upgrader::plan($this->project, $baseline, $candidate);
        $this->write('platform/plugins/b.js', 'concurrent edit');
        try {
            Upgrader::apply($this->project, $plan, $candidate);
            $this->fail('Concurrent changes must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('changed after preflight', $error->getMessage());
        }
        $this->assertSame('old', file_get_contents($this->project.'/platform/plugins/a.js'));
        symlink($this->directory, $this->project.'/linked');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('symlink');
        Upgrader::safePath($this->project, 'linked/external.php');
    }

    public function test_rejects_traversal_and_symlinks_in_archives(): void
    {
        try {
            $this->archive('unsafe.zip', ['../outside.php' => 'escape']);
            $this->fail('Path traversal must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Unsafe', $error->getMessage());
        }
        $path = $this->directory.'/symlink.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('linked', '/outside');
        $zip->setExternalAttributesName('linked', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Symlink in release');
        Upgrader::archive($path);
    }

    public function test_backup_contains_original_code_environment_uploads_and_database_files_with_private_permissions(): void
    {
        $this->write('.env', 'APP_KEY=original');
        $this->write('vendor/autoload.php', 'old vendor');
        $this->write('database/database.sqlite', 'database bytes');
        $this->write('storage/app/public/upload.jpg', 'upload bytes');
        $this->write('node_modules/package/index.js', 'rebuildable');
        $this->write('.git/config', 'git');
        symlink('storage/app/public', $this->project.'/public-storage');
        $backup = $this->project.'/storage/kern-upgrader/fixture';
        mkdir($backup, 0700, true);
        Upgrader::backupWebsite($this->project, $backup);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($backup.'/website.zip'));
        $this->assertSame('APP_KEY=original', $zip->getFromName('.env'));
        $this->assertSame('old vendor', $zip->getFromName('vendor/autoload.php'));
        $this->assertSame('database bytes', $zip->getFromName('database/database.sqlite'));
        $this->assertSame('upload bytes', $zip->getFromName('storage/app/public/upload.jpg'));
        $this->assertFalse($zip->getFromName('node_modules/package/index.js'));
        $this->assertFalse($zip->getFromName('storage/kern-upgrader/fixture/website.zip'));
        $zip->close();
        $metadata = json_decode(file_get_contents($backup.'/files.json'), true);
        $this->assertSame('storage/app/public', $metadata['symlinks']['public-storage']);
        $this->assertSame(0600, fileperms($backup.'/website.zip') & 0777);
        $this->assertSame(0600, fileperms($backup.'/files.json') & 0777);
    }

    private function cliFixture(): string
    {
        $old = ['platform/core/composer.json' => '{"name":"kern/platform"}', 'vite.config.ts' => 'stock build', 'platform/plugins/contact/a.js' => 'old jquery'];
        foreach ([...$old, '.env' => 'APP_KEY=original', 'storage/installed' => 'installed', 'storage/framework/.gitignore' => '*', 'artisan' => '<?php', 'vendor/autoload.php' => '<?php', 'bootstrap/app.php' => '<?php', 'composer.json' => '{"require":{"kern/platform":"*"}}', 'composer.lock' => '{"packages":[]}'] as $path => $contents) {
            $this->write($path, $contents);
        }
        mkdir($this->project.'/platform/packages');
        mkdir($this->project.'/resources/js', 0700, true);
        $this->archive('kern-cms-0.1.0.zip', $old);
        $this->archive('kern-cms-0.3.1.zip', ['composer.json' => '{"name":"binhtq1/kern-cms","type":"project","require":{"binhtq1/kern-core":"^0.3.1"}}', 'platform/plugins/contact/a.js' => 'native', 'vite.config.ts' => 'new build', 'resources/js/member.tsx' => 'member']);
        $this->archive('kern-core-0.3.1.zip', [
            'composer.json' => '{"name":"binhtq1/kern-core"}',
            'bin/migrate-to-core.php' => file_get_contents(__DIR__.'/../../platform/bin/migrate-to-core.php'),
            'core/base/src/Supports/Upgrade/DatabaseSnapshot.php' => file_get_contents(__DIR__.'/../../platform/core/base/src/Supports/Upgrade/DatabaseSnapshot.php'),
            'core/base/resources/version.json' => '{"version":"0.3.1"}',
            'core/base/resources/site-template/vite.config.ts' => 'new build',
            'core/base/resources/site-template/resources/js/member.tsx' => 'member',
            'core/base/resources/upgrade/0.1.0.json' => json_encode(['runtime' => ['platform/core/composer.json' => hash('sha256', $old['platform/core/composer.json'])], 'skeleton' => ['vite.config.ts' => hash('sha256', 'stock build')]]),
        ]);
        $metadata = ['version' => '0.3.1', 'baseline' => hash_file('sha256', $this->directory.'/kern-cms-0.1.0.zip'), 'hashes' => []];
        foreach (['kern-cms', 'kern-core'] as $name) {
            $metadata['hashes'][$name.'-0.3.1.zip'] = hash_file('sha256', $this->directory.'/'.$name.'-0.3.1.zip');
        }
        $path = $this->directory.'/kern-upgrade.php';
        file_put_contents($path, str_replace('__KERN_UPGRADE_RELEASE__', base64_encode(json_encode($metadata)), file_get_contents(__DIR__.'/../../kern-upgrade.php')));

        return $path;
    }

    private function cli(string $script, array $arguments, array $environment = []): Process
    {
        $process = new Process([PHP_BINARY, $script, '--to=0.3.1', '--project='.$this->project, '--artifacts='.$this->directory, ...$arguments], $this->project, $environment, timeout: 60);
        $process->run();

        return $process;
    }

    public function test_cli_check_is_read_only_and_reports_conflicts_without_loading_the_application(): void
    {
        $script = $this->cliFixture();
        $before = $this->fingerprint();
        $process = $this->cli($script, ['--check']);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('Check passed', $process->getOutput());
        $this->assertSame($before, $this->fingerprint());
        $this->write('platform/plugins/contact/a.js', 'customer code');
        $before = $this->fingerprint();
        $process = $this->cli($script, ['--check']);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('platform/plugins/contact/a.js', $process->getErrorOutput());
        $this->assertSame($before, $this->fingerprint());
        $this->assertDirectoryDoesNotExist($this->project.'/storage/kern-upgrader');
    }

    public function test_cli_rejects_tampered_archives_before_website_changes(): void
    {
        $script = $this->cliFixture();
        file_put_contents($this->directory.'/kern-core-0.3.1.zip', 'tampered');
        $before = $this->fingerprint();
        $process = $this->cli($script, ['--check']);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('SHA-256 mismatch', $process->getErrorOutput());
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_cli_check_reports_incompatible_customer_frontend_before_any_backup_or_conversion(): void
    {
        $script = $this->cliFixture();
        $this->write('platform/plugins/customer/resources/js/custom.js', 'jQuery(function () { window.custom = true; });');
        $before = $this->fingerprint();
        $process = $this->cli($script, ['--check']);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Custom plugin/theme source still requires jQuery', $process->getErrorOutput());
        $this->assertStringContainsString('platform/plugins/customer/resources/js/custom.js', $process->getErrorOutput());
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_failed_composer_update_retains_pre_conversion_code_database_snapshot_and_maintenance_mode(): void
    {
        $script = $this->cliFixture();
        $this->write('vendor/autoload.php', <<<'PHP'
<?php
namespace Illuminate\Support\Facades {
    class Schema { public static function hasTable($name) { return true; } }
    class DB {
        public static function connection() { return new class {
            public function getDriverName() { return 'sqlite'; }
            public function getConfig($key = null) { return $key ? getcwd().'/database/database.sqlite' : ['database' => getcwd().'/database/database.sqlite']; }
            public function getPdo() { return new \PDO('sqlite:'.getcwd().'/database/database.sqlite'); }
        }; }
    }
}
namespace { function config($name) { return 'original-key'; } }
PHP);
        $this->write('bootstrap/app.php', <<<'PHP'
<?php
return new class {
    public function make($name) { return new class { public function bootstrap() {} }; }
    public function isDownForMaintenance() { return is_file(getcwd().'/storage/framework/down'); }
};
PHP);
        $this->write('artisan', '<?php if ($argv[1] === "down") { file_put_contents("storage/framework/down", "maintenance"); } elseif (in_array($argv[1], ["optimize:clear", "cache:clear"], true)) { unlink("storage/framework/down"); }');
        $this->write('database/.gitignore', '*');
        $database = new PDO('sqlite:'.$this->project.'/database/database.sqlite');
        $database->exec("CREATE TABLE customer (value TEXT); INSERT INTO customer VALUES ('preserved')");
        $database = null;
        $binary = $this->directory.'/bin';
        mkdir($binary);
        file_put_contents($binary.'/composer', "#!/usr/bin/env php\n<?php if (in_array('--version', \$argv, true)) { exit(0); } file_put_contents(getenv('KERN_UPGRADE_TEST_TRACE'), json_encode(\$argv)); exit(23);\n");
        chmod($binary.'/composer', 0755);
        $originalComposer = file_get_contents($this->project.'/composer.json');
        $trace = $this->directory.'/composer-command.json';
        $process = $this->cli($script, [], ['PATH' => $binary.PATH_SEPARATOR.getenv('PATH'), 'KERN_UPGRADE_TEST_TRACE' => $trace]);
        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('Maintenance mode remains enabled', $process->getErrorOutput());
        $this->assertStringContainsString('Command failed: composer update', $process->getErrorOutput());
        $this->assertContains('binhtq1/kern-core:0.3.1', json_decode(file_get_contents($trace), true));
        $backups = glob($this->project.'/storage/kern-upgrader/*', GLOB_ONLYDIR);
        $this->assertCount(1, $backups);
        $backup = $backups[0];
        $this->assertFileExists($backup.'/database.sqlite');
        $snapshot = new PDO('sqlite:'.$backup.'/database.sqlite');
        $this->assertSame('preserved', $snapshot->query('SELECT value FROM customer')->fetchColumn());
        $zip = new ZipArchive;
        $zip->open($backup.'/website.zip');
        $this->assertSame($originalComposer, $zip->getFromName('composer.json'));
        $this->assertSame('APP_KEY=original', $zip->getFromName('.env'));
        $this->assertFalse($zip->getFromName('storage/framework/down'));
        $zip->close();
        $this->assertSame('failed', json_decode(file_get_contents($backup.'/status.json'), true)['status']);
        $this->assertSame('old jquery', file_get_contents($this->project.'/platform/plugins/contact/a.js'));
        $this->assertFileExists($this->project.'/storage/framework/down');
    }

    public function test_failed_database_prerequisites_stop_before_conversion_and_record_an_incomplete_backup(): void
    {
        $script = $this->cliFixture();
        $this->write('vendor/autoload.php', '<?php function config($key) { return null; }');
        $this->write('bootstrap/app.php', '<?php return new class { public function make($name) { return new class { public function bootstrap() {} }; } };');
        $original = file_get_contents($this->project.'/composer.json');
        $process = $this->cli($script, []);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('existing APP_KEY', $process->getErrorOutput());
        $this->assertStringContainsString('Backup incomplete', $process->getErrorOutput());
        $this->assertSame($original, file_get_contents($this->project.'/composer.json'));
        $this->assertSame('stock build', file_get_contents($this->project.'/vite.config.ts'));
        $this->assertFileDoesNotExist($this->project.'/storage/framework/down');
        $status = json_decode(file_get_contents(glob($this->project.'/storage/kern-upgrader/*/status.json')[0]), true);
        $this->assertFalse($status['backup_complete']);
        $this->assertSame('failed', $status['status']);
    }

    public function test_repeated_upgrade_requires_matching_completed_state_lock_and_installed_runtime(): void
    {
        $script = $this->cliFixture();
        $this->write('storage/app/kern/upgrade.json', '{"version":"0.3.1","completed_at":"2026-10-29T00:00:00Z"}');
        $this->write('composer.lock', '{"packages":[{"name":"binhtq1/kern-core","version":"v0.3.1"}]}');
        $this->write('composer.json', '{"require":{"binhtq1/kern-core":"^0.3.1"}}');
        $this->write('vendor/binhtq1/kern-core/core/base/resources/version.json', '{"version":"0.3.1"}');
        $before = $this->fingerprint();
        $process = $this->cli($script, []);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('already installed', $process->getOutput());
        $this->assertSame($before, $this->fingerprint());
        $this->write('vendor/binhtq1/kern-core/core/base/resources/version.json', '{"version":"0.3.0"}');
        $process = $this->cli($script, []);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringNotContainsString('already installed', $process->getOutput());
    }
}
