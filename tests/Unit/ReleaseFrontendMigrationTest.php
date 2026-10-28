<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use ZipArchive;

class ReleaseFrontendMigrationTest extends TestCase
{
    private string $directory;

    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kern-release-frontend-'.bin2hex(random_bytes(8));
        $this->project = $this->directory.'/site';
        $this->write('storage/installed', 'installed');
        $this->write('.env', 'APP_KEY=fixture');
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

    private function write(string $path, string $content): void
    {
        $destination = $this->project.'/'.$path;
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0700, true);
        }
        file_put_contents($destination, $content);
    }

    private function archive(array $files): string
    {
        $path = $this->directory.'/candidate.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('composer.json', '{"name":"binhtq1/kern-cms","type":"project"}');
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function frontend(string $mode, ?string $archive = null): Process
    {
        $process = new Process([PHP_BINARY, __DIR__.'/../fixtures/upgrade-stock-frontend.php', $mode, $this->project, $this->directory.'/pristine.json', $archive ?? '']);
        $process->run();

        return $process;
    }

    public function test_explicit_stock_update_preserves_custom_source_assets_and_data(): void
    {
        $stock = [
            'platform/plugins/contact/resources/js/contact.js' => 'old source',
            'platform/plugins/contact/public/js/contact.js' => 'old bundle',
            'public/vendor/core/plugins/contact/js/contact.js' => 'old published bundle',
            'platform/themes/magazine/layouts/default.blade.php' => 'stock layout',
            'public/themes/magazine/build/entry.js' => 'old theme bundle',
            'platform/themes/ripple/public/plugins/jquery.js' => 'old retired file',
        ];
        foreach ($stock as $path => $content) {
            $this->write($path, $content);
        }
        $this->assertSame(0, $this->frontend('capture')->getExitCode());
        $this->write('platform/themes/magazine/layouts/default.blade.php', 'customer layout');
        $this->write('platform/plugins/site-custom/plugin.json', 'customer plugin');
        $this->write('public/themes/magazine/site-custom.css', 'customer css');
        $this->write('storage/app/public/upload.txt', 'customer upload');
        $archive = $this->archive([
            'platform/plugins/contact/resources/js/contact.js' => 'native source',
            'platform/plugins/contact/public/js/contact.js' => 'native bundle',
            'public/vendor/core/plugins/contact/js/contact.js' => 'native published bundle',
            'platform/themes/magazine/layouts/default.blade.php' => 'stock layout',
            'public/themes/magazine/build/entry.js' => 'native theme bundle',
            'resources/js/member.tsx' => 'new member entry',
        ]);
        $process = $this->frontend('apply', $archive);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('native source', file_get_contents($this->project.'/platform/plugins/contact/resources/js/contact.js'));
        $this->assertSame('native published bundle', file_get_contents($this->project.'/public/vendor/core/plugins/contact/js/contact.js'));
        $this->assertSame('native theme bundle', file_get_contents($this->project.'/public/themes/magazine/build/entry.js'));
        $this->assertSame('customer layout', file_get_contents($this->project.'/platform/themes/magazine/layouts/default.blade.php'));
        $this->assertSame('customer plugin', file_get_contents($this->project.'/platform/plugins/site-custom/plugin.json'));
        $this->assertSame('customer css', file_get_contents($this->project.'/public/themes/magazine/site-custom.css'));
        $this->assertSame('customer upload', file_get_contents($this->project.'/storage/app/public/upload.txt'));
        $this->assertFileDoesNotExist($this->project.'/platform/themes/ripple/public/plugins/jquery.js');
        $backups = glob($this->project.'/storage/frontend-fixture-backups/*/platform/plugins/contact/resources/js/contact.js');
        $this->assertCount(1, $backups);
        $this->assertSame('old source', file_get_contents($backups[0]));
        $this->assertSame(0, $this->frontend('apply', $archive)->getExitCode(), 'The deliberate fixture update must be idempotent.');
    }

    public function test_customized_upstream_file_conflicts_before_any_stock_asset_is_changed(): void
    {
        $this->write('platform/plugins/contact/resources/js/contact.js', 'old source');
        $this->write('public/vendor/core/plugins/contact/js/contact.js', 'old bundle');
        $this->assertSame(0, $this->frontend('capture')->getExitCode());
        $this->write('platform/plugins/contact/resources/js/contact.js', 'customer source');
        $process = $this->frontend('apply', $this->archive([
            'platform/plugins/contact/resources/js/contact.js' => 'native source',
            'public/vendor/core/plugins/contact/js/contact.js' => 'native bundle',
        ]));
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Customized stock frontend files conflict', $process->getErrorOutput());
        $this->assertSame('customer source', file_get_contents($this->project.'/platform/plugins/contact/resources/js/contact.js'));
        $this->assertSame('old bundle', file_get_contents($this->project.'/public/vendor/core/plugins/contact/js/contact.js'));
        $this->assertDirectoryDoesNotExist($this->project.'/storage/frontend-fixture-backups');
    }

    public function test_new_candidate_file_cannot_overwrite_a_customer_file(): void
    {
        $this->assertSame(0, $this->frontend('capture')->getExitCode());
        $this->write('resources/js/member.tsx', 'customer entry');
        $process = $this->frontend('apply', $this->archive(['resources/js/member.tsx' => 'stock entry']));
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame('customer entry', file_get_contents($this->project.'/resources/js/member.tsx'));
    }

    private function converter(string $version, ?string $memberEntry = null): Process
    {
        $runtime = $this->directory.'/runtime';
        foreach (['bin', 'core/base/resources/upgrade', 'core/base/resources/site-template/resources/js'] as $path) {
            mkdir($runtime.'/'.$path, 0700, true);
        }
        copy(__DIR__.'/../../platform/bin/migrate-to-core.php', $runtime.'/bin/migrate-to-core.php');
        file_put_contents($runtime.'/core/base/resources/version.json', json_encode(['version' => $version]));
        file_put_contents($runtime.'/core/base/resources/site-template/vite.config.ts', 'new stock vite');
        file_put_contents($runtime.'/core/base/resources/site-template/resources/js/member.tsx', 'stock member entry');
        $this->write('platform/core/composer.json', '{"name":"kern/platform"}');
        mkdir($this->project.'/platform/packages', 0700, true);
        $this->write('vite.config.ts', 'old stock vite');
        $this->write('resources/js/admin.tsx', 'old stock admin');
        $this->write('composer.json', '{"require":{"kern/platform":"*","customer/site-package":"^1.0"}}');
        if ($memberEntry !== null) {
            $this->write('resources/js/member.tsx', $memberEntry);
        }
        file_put_contents($runtime.'/core/base/resources/upgrade/0.1.0.json', json_encode([
            'runtime' => ['platform/core/composer.json' => hash_file('sha256', $this->project.'/platform/core/composer.json')],
            'skeleton' => ['vite.config.ts' => hash_file('sha256', $this->project.'/vite.config.ts')],
        ]));
        $process = new Process([PHP_BINARY, $runtime.'/bin/migrate-to-core.php', '--project='.$this->project]);
        $process->run();

        return $process;
    }

    public function test_converter_requires_the_built_target_version_and_seeds_missing_member_entry(): void
    {
        $process = $this->converter('0.3.0');
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $manifest = json_decode(file_get_contents($this->project.'/composer.json'), true);
        $this->assertSame('^0.3.0', $manifest['require']['binhtq1/kern-core']);
        $this->assertSame('^1.0', $manifest['require']['customer/site-package']);
        $this->assertArrayNotHasKey('kern/platform', $manifest['require']);
        $this->assertSame('stock member entry', file_get_contents($this->project.'/resources/js/member.tsx'));
    }

    public function test_converter_preserves_an_existing_customer_member_entry(): void
    {
        $process = $this->converter('1.4.2', 'customer member entry');
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('customer member entry', file_get_contents($this->project.'/resources/js/member.tsx'));
        $this->assertSame('^1.4.2', json_decode(file_get_contents($this->project.'/composer.json'), true)['require']['binhtq1/kern-core']);
    }

    public function test_converter_rejects_invalid_target_metadata_before_project_changes(): void
    {
        $process = $this->converter('v0.3.0');
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('stable target version', $process->getErrorOutput());
        $this->assertSame('old stock vite', file_get_contents($this->project.'/vite.config.ts'));
        $this->assertDirectoryDoesNotExist($this->project.'/storage/kern-conversions');
    }
}
