<?php

namespace Tests\Unit;

use Kern\Base\Supports\Upgrade\NativeFrontendCompatibility;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NativeFrontendCompatibilityTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kern-native-frontend-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/resources/js', 0700, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_legacy_plugin_sources_are_rejected_without_changing_site_files(): void
    {
        $path = $this->directory.'/resources/js/contact.js';
        $source = '$(document).on("submit", ".contact-form", () => $.ajax({}));';
        file_put_contents($path, $source);
        try {
            (new NativeFrontendCompatibility)->assertPathsAreSupported(['plugin customer-contact' => $this->directory]);
            $this->fail('The legacy frontend must be migrated first.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('plugin customer-contact: resources/js/contact.js', $exception->getMessage());
            $this->assertStringContainsString('Core will not overwrite your customizations', $exception->getMessage());
        }
        $this->assertSame($source, file_get_contents($path));
    }

    public function test_theme_asset_dependencies_are_rejected(): void
    {
        file_put_contents($this->directory.'/config.php', '<?php $theme->asset()->add("contact", "js/contact.js", ["jquery"]);');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('theme customer-theme: config.php');
        (new NativeFrontendCompatibility)->assertPathsAreSupported(['theme customer-theme' => $this->directory]);
    }

    public function test_native_sources_and_documentation_mentions_are_allowed(): void
    {
        file_put_contents($this->directory.'/resources/js/contact.js', <<<'JS'
            // Previously used jQuery(document).ready().
            /* Migration example: $('form').submit() */
            document.addEventListener('submit', event => fetch(event.target.action, {method: 'POST', body: new FormData(event.target)}));
            JS);
        mkdir($this->directory.'/public/libraries', 0700, true);
        file_put_contents($this->directory.'/public/libraries/optional-bridge.js', 'if (window.jQuery) window.jQuery.fn.optionalBridge = function () {};');
        (new NativeFrontendCompatibility)->assertPathsAreSupported(['plugin native-contact' => $this->directory]);
        $this->assertFileExists($this->directory.'/resources/js/contact.js');
    }
}
