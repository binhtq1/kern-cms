<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminExtensionRegistry;
use Kern\Base\Supports\AdminRenderer;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminExtensionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_explicit_module_opt_in_with_matching_built_pages_is_supported(): void
    {
        $this->withExtension(function (string $root): void {
            $registry = app(AdminExtensionRegistry::class);
            $this->assertFalse($registry->supports('extra', 'pages'));
            config(['core.base.admin.extensions.extra.modules' => ['pages']]);
            $this->assertTrue($registry->supports('extra', 'pages'));
            $this->assertFalse($registry->supports('extra', 'users'));
            $this->assertFalse($registry->supports('../extra', 'pages'));
            unlink($root.'/platform/plugins/extra/resources/js/admin/Screens/Index.tsx');
            $this->assertFalse($registry->supports('extra', 'pages'));
        });
    }

    public function test_missing_stale_or_invalid_build_evidence_rejects_unregistered_extension(): void
    {
        $this->withExtension(function (string $root): void {
            config(['core.base.admin.extensions.extra.modules' => ['pages']]);
            $registry = app(AdminExtensionRegistry::class);
            $bundle = $root.'/public/build/admin/admin-registry.json';
            $original = file_get_contents($bundle);
            unlink($bundle);
            $this->assertFalse($registry->supports('extra', 'pages'));
            file_put_contents($bundle, '{invalid');
            $this->assertFalse($registry->supports('extra', 'pages'));
            file_put_contents($bundle, $original);
            file_put_contents($root.'/platform/plugins/extra/resources/js/admin/registration.json', "\n", FILE_APPEND);
            $this->assertFalse($registry->supports('extra', 'pages'));
        });
    }

    public function test_even_matching_hash_cannot_register_traversal_or_missing_page_contracts(): void
    {
        $this->withExtension(function (string $root): void {
            config(['core.base.admin.extensions.extra.modules' => ['pages']]);
            $registration = $root.'/platform/plugins/extra/resources/js/admin/registration.json';
            $bundle = $root.'/public/build/admin/admin-registry.json';
            foreach (['../Screens', '/Screens', 'Screens//Nested'] as $directory) {
                file_put_contents($registration, json_encode(['version' => 1, 'namespaces' => ['Extra' => $directory]]));
                file_put_contents($bundle, json_encode(['version' => 1, 'packages' => ['plugins/extra' => ['hash' => hash_file('sha256', $registration), 'pages' => ['Extra/Index']]]]));
                $this->assertFalse(app(AdminExtensionRegistry::class)->supports('extra', 'pages'));
            }
        });
    }

    public function test_matching_extension_registration_is_not_sufficient_when_build_assets_are_missing(): void
    {
        $this->withExtension(function (string $root): void {
            config(['core.base.admin.extensions.extra.modules' => ['pages']]);
            unlink($root.'/public/build/admin/assets/admin.js');

            $this->assertFalse(app(AdminExtensionRegistry::class)->supports('extra', 'pages'));
        });
    }

    public function test_stale_registry_evidence_cannot_register_a_page_absent_from_the_bundle_manifest(): void
    {
        $this->withExtension(function (string $root): void {
            config(['core.base.admin.extensions.extra.modules' => ['pages']]);
            $path = $root.'/public/build/admin/manifest.json';
            $manifest = json_decode(file_get_contents($path), true);
            unset($manifest['platform/plugins/extra/resources/js/admin/Screens/Index.tsx']);
            file_put_contents($path, json_encode($manifest));

            $this->assertFalse(app(AdminExtensionRegistry::class)->supports('extra', 'pages'));
            file_put_contents($root.'/public/admin.hot', 'http://localhost:5173');
            $this->assertTrue(app(AdminExtensionRegistry::class)->supports('extra', 'pages'));
        });
    }

    public function test_missing_or_invalid_admin_assets_fail_fast_with_actionable_503_before_writes(): void
    {
        $this->withExtension(function (string $root): void {
            config(['core.base.admin.modules.operations' => 'inertia', 'packages.installer.installer.enabled' => false]);
            $written = false;
            Route::middleware(['web', HandleAdminInertiaRequests::class.':operations'])->group(function () use (&$written): void {
                Route::get('/admin/test-assets', fn () => response('<h1>Should not render</h1>'));
                Route::post('/admin/test-assets', function () use (&$written) {
                    $written = true;

                    return response()->json(['saved' => true]);
                });
            });
            $manifest = $root.'/public/build/admin/manifest.json';
            $original = file_get_contents($manifest);
            foreach ([null, '{broken', '{}', json_encode(['resources/js/admin.tsx' => ['isEntry' => true, 'file' => 'assets/missing.js']]), json_encode(['resources/js/admin.tsx' => ['isEntry' => true, 'file' => 'assets/admin.js', 'dynamicImports' => ['missing-page']]])] as $content) {
                if ($content === null) {
                    unlink($manifest);
                } else {
                    file_put_contents($manifest, $content);
                }

                $this->get('/admin/test-assets')->assertStatus(503)->assertJsonPath('error', true)->assertSee('npm run build:admin');
                $this->get('/admin/test-assets', ['X-Inertia' => 'true'])->assertStatus(503)->assertJsonPath('error', true);
                $this->postJson('/admin/test-assets')->assertStatus(503)->assertJsonPath('error', true);
                $this->assertFalse($written, 'An unavailable admin bundle must block controller writes.');
            }
            file_put_contents($manifest, $original);
            unlink($root.'/public/build/admin/assets/admin.css');
            $this->get('/admin/test-assets')->assertStatus(503)->assertJsonPath('error', true);
        });
    }

    public function test_per_module_blade_setting_no_longer_blocks_registered_admin_routes(): void
    {
        config(['core.base.admin.modules.operations' => 'blade', 'core.base.admin.route_modules.readiness-records' => 'operations', 'packages.installer.installer.enabled' => false]);
        $written = false;
        Route::middleware(['web', HandleAdminInertiaRequests::class.':operations'])->group(function () use (&$written): void {
            Route::get('/admin/readiness/edit/{record}', fn () => Inertia::render('Operations/Index'))->name('readiness-records.edit');
            Route::put('/admin/readiness/{record}', function () use (&$written): \Symfony\Component\HttpFoundation\Response {
                $written = true;

                return redirect('/admin/readiness/edit/7');
            })->name('readiness-records.update');
        });
        Route::getRoutes()->refreshNameLookups();
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $this->put('/admin/readiness/7?ref_lang=vi&record=999', [], $headers)->assertRedirect('/admin/readiness/edit/7');
        $this->assertTrue($written);
        $this->get('/admin/readiness/edit/7', $headers)->assertOk()->assertJsonPath('component', 'Operations/Index');
    }

    public function test_valid_production_bundle_or_development_hot_file_is_ready(): void
    {
        $this->withExtension(function (string $root): void {
            $renderer = app(AdminRenderer::class);
            $this->assertNull($renderer->assetFallbackReason());
            unlink($root.'/public/build/admin/manifest.json');
            file_put_contents($root.'/public/admin.hot', 'http://localhost:5173');
            $this->assertNull($renderer->assetFallbackReason());
            file_put_contents($root.'/public/admin.hot', 'not a URL');
            $this->assertNotNull($renderer->assetFallbackReason());
        });
    }

    public function test_legacy_admin_routes_are_blocked_before_reads_and_writes_unless_they_opt_into_inertia(): void
    {
        $written = false;
        AdminHelper::registerRoutes(function () use (&$written): void {
            Route::get('legacy-screen', fn () => response('Legacy HTML'))->name('acceptance.legacy.index');
            Route::put('legacy-screen', function () use (&$written): Response {
                $written = true;

                return response('Legacy write ran');
            })->name('acceptance.legacy.update');
            Route::get('react-screen', fn () => Inertia::render('System/Index'))
                ->middleware(HandleAdminInertiaRequests::class.':operations')
                ->name('acceptance.react.index');
            Route::get('legacy-response-screen', fn () => response('<h1>Legacy screen</h1>'))
                ->middleware(HandleAdminInertiaRequests::class.':operations')
                ->name('acceptance.react-legacy-response.index');
        });
        Route::getRoutes()->refreshNameLookups();
        $this->actingAs(UserFactory::new()->superUser()->create());

        $url = route('acceptance.legacy.index');
        $this->get($url)->assertStatus(503)->assertDontSee('Legacy HTML');
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $this->get($url, $headers)->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->get($url, $headers)
            ->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->put(route('acceptance.legacy.update'), [], $headers)->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->assertFalse($written, 'Legacy route handlers must not run before compatibility is restored.');

        $this->get(route('acceptance.react.index'), $headers)->assertOk()->assertJsonPath('component', 'System/Index');
        $this->get(route('acceptance.react-legacy-response.index'), $headers)
            ->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_unknown_active_plugin_cannot_break_auth_or_plugin_recovery_screens(): void
    {
        $this->withoutVite();
        $this->actingAs(UserFactory::new()->superUser()->create());
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $this->assertFalse(app(PluginService::class)->activate('block')['error']);
        config(['core.base.admin.supported_plugins.pages' => []]);

        auth()->logout();
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $this->get(route('access.login'), $headers)->assertOk()->assertJsonPath('component', 'Auth/Form');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('plugins.index'), $headers)->assertOk()->assertJsonPath('component', 'System/Plugins');
        $this->get(route('pages.index'), $headers)->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_logout_remains_available_when_admin_assets_are_missing(): void
    {
        $this->withExtension(function (string $root): void {
            $this->actingAs(UserFactory::new()->superUser()->create());
            unlink($root.'/public/build/admin/manifest.json');

            $this->get(route('access.logout'))->assertRedirect();
            $this->assertGuest();
        });
    }

    protected function withExtension(callable $assertions): void
    {
        $basePath = $this->app->basePath();
        $publicPath = $this->app->publicPath();
        $root = sys_get_temp_dir().'/kern-admin-registry-'.bin2hex(random_bytes(8));
        $source = $root.'/platform/plugins/extra/resources/js/admin';
        File::ensureDirectoryExists($source.'/Screens');
        File::ensureDirectoryExists($root.'/public/build/admin/assets');
        file_put_contents($root.'/public/build/admin/assets/admin.js', 'export {};');
        file_put_contents($root.'/public/build/admin/assets/admin.css', 'body {}');
        file_put_contents($root.'/public/build/admin/assets/extra.js', 'export default function Index() {}');
        file_put_contents($root.'/public/build/admin/manifest.json', json_encode(['resources/js/admin.tsx' => ['file' => 'assets/admin.js', 'isEntry' => true, 'css' => ['assets/admin.css']], 'platform/plugins/extra/resources/js/admin/Screens/Index.tsx' => ['file' => 'assets/extra.js', 'isDynamicEntry' => true]]));
        file_put_contents($source.'/Screens/Index.tsx', 'export default function Index() {}');
        file_put_contents($source.'/registration.json', json_encode(['version' => 1, 'namespaces' => ['Extra' => 'Screens']]));
        file_put_contents($root.'/public/build/admin/admin-registry.json', json_encode(['version' => 1, 'packages' => ['plugins/extra' => ['hash' => hash_file('sha256', $source.'/registration.json'), 'pages' => ['Extra/Index']]]]));
        $this->app->setBasePath($root);
        $this->app->usePublicPath($root.'/public');
        try {
            $assertions($root);
        } finally {
            $this->app->setBasePath($basePath);
            $this->app->usePublicPath($publicPath);
            File::deleteDirectory($root);
        }
    }
}
