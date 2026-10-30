<?php

namespace Tests\Feature;

use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use InvalidArgumentException;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Services\ThemeService;
use Kern\Theme\Supports\ThemeVite;
use Tests\TestCase;

class ThemeViteTest extends TestCase
{
    private string $theme;

    private string $temporaryPublicPath;

    private string $originalPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->theme = 'vite-test-'.bin2hex(random_bytes(6));
        $this->temporaryPublicPath = sys_get_temp_dir().'/'.$this->theme;
        $this->originalPublicPath = $this->app->publicPath();
        File::ensureDirectoryExists(theme_path($this->theme.'/public/build/assets'));
        File::ensureDirectoryExists($this->temporaryPublicPath);
        $this->app->usePublicPath($this->temporaryPublicPath);
        Theme::swap(new class($this->theme)
        {
            public ?string $parent = null;

            public function __construct(private string $name) {}

            public function getThemeName(): string
            {
                return $this->name;
            }

            public function getPublicThemeName(): string
            {
                return 'public-alias';
            }

            public function getInheritTheme(): ?string
            {
                return $this->parent;
            }
        });
    }

    protected function tearDown(): void
    {
        $this->app->usePublicPath($this->originalPublicPath);
        File::deleteDirectory(theme_path($this->theme));
        File::deleteDirectory($this->temporaryPublicPath);

        parent::tearDown();
    }

    public function test_published_theme_renders_manifest_assets_without_changing_admin_vite(): void
    {
        $this->writeMetadata(['assets/css/site.css', 'assets/js/site.js']);
        $prefix = 'platform/themes/'.$this->theme.'/';
        File::put(theme_path($this->theme.'/public/build/manifest.json'), json_encode([
            $prefix.'assets/css/site.css' => ['file' => 'assets/site-hash.css', 'src' => $prefix.'assets/css/site.css', 'isEntry' => true],
            $prefix.'assets/js/site.js' => ['file' => 'assets/site-hash.js', 'src' => $prefix.'assets/js/site.js', 'isEntry' => true, 'css' => ['assets/import-hash.css']],
        ], JSON_THROW_ON_ERROR));
        foreach (['site-hash.css', 'site-hash.js', 'import-hash.css'] as $file) {
            File::put(theme_path($this->theme.'/public/build/assets/'.$file), 'fixture');
        }
        Vite::useHotFile(public_path('admin.hot'))->useBuildDirectory('build/admin');

        $result = app(ThemeService::class)->publishAssets($this->theme);
        $html = app(ThemeVite::class)->render()->toHtml();

        $this->assertFalse($result['error']);
        $this->assertFileExists(public_path('themes/public-alias/build/manifest.json'));
        $this->assertFileExists(public_path('themes/public-alias/build/assets/site-hash.js'));
        $this->assertStringContainsString('/themes/public-alias/build/assets/site-hash.css', $html);
        $this->assertStringContainsString('/themes/public-alias/build/assets/site-hash.js', $html);
        $this->assertStringContainsString('/themes/public-alias/build/assets/import-hash.css', $html);
        $this->assertStringContainsString('type="module"', $html);
        $this->assertSame(public_path('admin.hot'), Vite::hotFile());
    }

    public function test_development_server_uses_theme_hot_file_without_admin_hot_file(): void
    {
        $this->writeMetadata(['assets/js/site.js']);
        File::ensureDirectoryExists(public_path('themes'));
        File::put(public_path('themes/'.$this->theme.'.hot'), 'http://localhost:5199');
        Vite::useHotFile(public_path('admin.hot'));

        $html = app(ThemeVite::class)->render()->toHtml();

        $this->assertStringContainsString('http://localhost:5199/@vite/client', $html);
        $this->assertStringContainsString('http://localhost:5199/platform/themes/'.$this->theme.'/assets/js/site.js', $html);
        $this->assertSame(public_path('admin.hot'), Vite::hotFile());
    }

    public function test_legacy_theme_without_vite_configuration_renders_no_vite_tags(): void
    {
        File::put(theme_path($this->theme.'/theme.json'), '{}');

        $this->assertSame('', app(ThemeVite::class)->render()->toHtml());
    }

    public function test_inherited_theme_assets_render_before_the_child_theme_assets(): void
    {
        $parent = $this->theme.'-parent';
        File::ensureDirectoryExists(theme_path($parent));
        File::put(theme_path($parent.'/theme.json'), json_encode(['vite' => ['entrypoints' => ['assets/js/parent.js']]], JSON_THROW_ON_ERROR));
        $this->writeMetadata(['assets/js/child.js']);
        File::ensureDirectoryExists(public_path('themes'));
        File::put(public_path('themes/'.$parent.'.hot'), 'http://localhost:5198');
        File::put(public_path('themes/'.$this->theme.'.hot'), 'http://localhost:5199');
        Theme::getFacadeRoot()->parent = $parent;

        try {
            $html = app(ThemeVite::class)->render()->toHtml();

            $this->assertStringContainsString('http://localhost:5198/platform/themes/'.$parent.'/assets/js/parent.js', $html);
            $this->assertStringContainsString('http://localhost:5199/platform/themes/'.$this->theme.'/assets/js/child.js', $html);
            $this->assertLessThan(strpos($html, 'assets/js/child.js'), strpos($html, 'assets/js/parent.js'));
        } finally {
            File::deleteDirectory(theme_path($parent));
        }
    }

    public function test_invalid_entrypoint_is_rejected(): void
    {
        $this->writeMetadata(['assets/../../secret.js']);
        $this->expectException(InvalidArgumentException::class);

        app(ThemeVite::class)->render();
    }

    public function test_missing_production_manifest_is_reported(): void
    {
        $this->writeMetadata(['assets/js/site.js']);
        $this->expectException(ViteManifestNotFoundException::class);

        app(ThemeVite::class)->render();
    }

    public function test_laravel_vite_fake_does_not_require_a_theme_manifest(): void
    {
        $this->writeMetadata(['assets/js/site.js']);
        $this->withoutVite();

        $this->assertSame('', app(ThemeVite::class)->render()->toHtml());
    }

    private function writeMetadata(array $entries): void
    {
        File::put(theme_path($this->theme.'/theme.json'), json_encode(['vite' => ['entrypoints' => $entries]], JSON_THROW_ON_ERROR));
    }
}
