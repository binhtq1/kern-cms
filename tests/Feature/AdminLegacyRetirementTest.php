<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kern\Backup\Http\Controllers\BackupController;
use Kern\Backup\Supports\Backup;
use Kern\Base\Facades\Assets;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Language\Models\Language as LanguageModel;
use Kern\Member\Models\Member;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Tests\TestCase;

class AdminLegacyRetirementTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    private string $temporaryLanguages;

    protected function setUp(): void
    {
        parent::setUp();

        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
        $this->temporaryStorage = sys_get_temp_dir().'/kern-admin-retirement-'.uniqid();
        $this->temporaryLanguages = sys_get_temp_dir().'/kern-admin-retirement-lang-'.uniqid();
        File::ensureDirectoryExists($this->temporaryStorage.'/framework/cache/data');
        File::ensureDirectoryExists($this->temporaryStorage.'/logs');
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        File::deleteDirectory($this->temporaryLanguages);
        parent::tearDown();
    }

    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
            'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json'))
                ? hash_file('sha256', public_path('build/admin/manifest.json'))
                : '',
        ];
    }

    public function test_admin_initial_visits_use_the_react_inertia_shell_without_legacy_bundle_urls(): void
    {
        $login = $this->get(route('access.login'))->assertOk()->assertViewIs('core/base::admin.app');
        $this->assertInertiaShell($login->getContent());

        $this->actingAs(UserFactory::new()->superUser()->create());

        foreach (['dashboard.index', 'pages.index', 'system.index'] as $routeName) {
            $response = $this->get(route($routeName))->assertOk()->assertViewIs('core/base::admin.app');
            $this->assertInertiaShell($response->getContent());
        }
    }

    public function test_admin_inertia_visits_resolve_registered_react_pages(): void
    {
        $this->get(route('access.login'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Auth/Form');

        $this->actingAs(UserFactory::new()->superUser()->create());

        $this->get(route('dashboard.index'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard/Index');
        $this->get(route('pages.index'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Pages/Index');
    }

    public function test_json_admin_tables_return_structured_react_data_without_blade_cells(): void
    {
        Http::preventStrayRequests();

        foreach (['audit-log', 'request-log'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }

        $this->actingAs(UserFactory::new()->superUser()->create());

        $responses = [
            ['system.info', 'System/Information', ['props' => ['application', 'server', 'packages']]],
            ['api.sanctum-token.index', 'System/Console', ['props' => ['rows', 'columns']]],
            ['audit-log.index', 'System/Console', ['props' => ['rows', 'columns']]],
            ['request-log.index', 'System/Console', ['props' => ['rows', 'columns']]],
        ];

        foreach ($responses as [$routeName, $component, $structure]) {
            $response = $this->getJson(route($routeName))
                ->assertOk()
                ->assertJsonPath('component', $component)
                ->assertJsonStructure($structure);

            $this->assertStringNotContainsString('<table', strtolower($response->getContent()), $routeName.' must not return a Blade-rendered table.');
            $this->assertStringNotContainsString('<td', strtolower($response->getContent()), $routeName.' must not return Blade table cells.');
        }
    }

    public function test_language_json_mutations_return_data_and_backup_json_never_runs_a_real_backup(): void
    {
        foreach (['language', 'backup'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }

        File::copyDirectory(base_path('lang/en'), $this->temporaryLanguages.'/en');
        foreach (['vendor/core', 'vendor/packages', 'vendor/plugins'] as $directory) {
            File::ensureDirectoryExists($this->temporaryLanguages.'/'.$directory);
        }
        $this->app->useLangPath($this->temporaryLanguages);
        Theme::setThemeName('ripple');

        LanguageModel::query()->create([
            'lang_name' => 'English',
            'lang_code' => 'en_US',
            'lang_locale' => 'en',
            'lang_flag' => 'us',
            'lang_order' => 1,
            'lang_is_default' => true,
        ]);
        $this->actingAs(UserFactory::new()->superUser()->create());

        $language = $this->postJson(route('languages.store'), [
            'lang_name' => 'Vietnamese',
            'lang_code' => 'vi',
            'lang_locale' => 'vi',
            'lang_flag' => 'vn',
            'lang_is_rtl' => false,
            'lang_order' => 2,
        ])->assertOk();
        $this->assertFalse($language->json('error'), $language->getContent());
        $this->assertSame('vi', $language->json('data.lang_code'));
        $languageId = $language->json('data.lang_id');

        $updated = $this->postJson(route('languages.edit'), [
            'lang_id' => $languageId,
            'lang_name' => 'Vietnamese updated',
            'lang_code' => 'vi',
            'lang_locale' => 'vi',
            'lang_flag' => 'vn',
            'lang_is_rtl' => false,
            'lang_order' => 2,
        ])->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.lang_name', 'Vietnamese updated');
        $this->assertStringNotContainsString('<table', strtolower($updated->getContent()));
        $this->assertStringNotContainsString('<td', strtolower($updated->getContent()));

        $backup = $this->postJson(route('backups.create'), ['name' => 'Retirement no-op fixture'])
            ->assertOk()->assertJsonPath('error', true);
        $this->assertStringNotContainsString('<table', strtolower($backup->getContent()));
        $this->assertStringNotContainsString('<td', strtolower($backup->getContent()));
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/backup');

        // Exercise the successful JSON payload without invoking a real database dump
        // or creating backup files.
        $backupService = \Mockery::mock(Backup::class);
        $backupService->shouldReceive('createBackupFolder')->once()->with('Mocked snapshot', 'No filesystem writes')->andReturn([
            'key' => 'mocked-key',
            'data' => ['name' => 'Mocked snapshot', 'description' => 'No filesystem writes', 'date' => '2026-10-17 00:00:00'],
        ]);
        $backupService->shouldReceive('backupDb')->once()->andReturnTrue();
        $backupService->shouldReceive('backupFolder')->once()->andReturnTrue();
        $controller = new BackupController($backupService);
        (new \ReflectionProperty($controller, 'databaseDriver'))->setValue($controller, 'mysql');
        $this->app->instance(BackupController::class, $controller);
        Route::getRoutes()->getByName('backups.create')->flushController();

        $successfulBackup = $this->postJson(route('backups.create'), [
            'name' => 'Mocked snapshot',
            'description' => 'No filesystem writes',
        ])->assertOk()->assertJsonPath('error', false)
            ->assertJsonPath('data.key', 'mocked-key')
            ->assertJsonPath('data.name', 'Mocked snapshot')
            ->assertJsonPath('data.description', 'No filesystem writes');
        $this->assertStringNotContainsString('<table', strtolower($successfulBackup->getContent()));
        $this->assertStringNotContainsString('<td', strtolower($successfulBackup->getContent()));
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/backup');
    }

    public function test_legacy_asset_api_calls_cannot_reintroduce_bundles_on_an_admin_route(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());

        Route::middleware(HandleAdminInertiaRequests::class)->get('/admin/legacy-asset-api-check', function () {
            Assets::usingVueJS()
                ->addScripts(['app', 'vue', 'vue-app', 'core'])
                ->addStyles(['core'])
                ->addScriptsDirectly('/vendor/core/core/base/js/vue-app.js')
                ->addStylesDirectly('/vendor/core/core/base/css/core.css');

            return response()->json([
                'hasVue' => Assets::hasVueJs(),
                'scripts' => Assets::getScripts(),
                'styles' => Assets::getStyles(),
                'vueScript' => Assets::scriptToHtml('vue'),
                'coreScript' => Assets::scriptToHtml('core'),
                'coreStyle' => Assets::styleToHtml('core'),
                'header' => Assets::renderHeader(),
                'footer' => Assets::renderFooter(),
            ]);
        });

        $response = $this->getJson('/admin/legacy-asset-api-check')->assertOk()->assertJsonPath('hasVue', false);
        $content = strtolower($response->getContent());

        foreach ([
            'jquery.min.js',
            'vue.global.min.js',
            'vue-app.js',
            '/js/core.js',
            '/css/core.css',
        ] as $legacyAsset) {
            $this->assertStringNotContainsString($legacyAsset, $content, 'The Assets API must not emit '.$legacyAsset.' for registered React admin routes.');
        }
        $this->assertEmpty($response->json('vueScript'));
        $this->assertEmpty($response->json('coreScript'));
        $this->assertEmpty($response->json('coreStyle'));
        $this->assertEmpty($response->json('header'));
        $this->assertEmpty($response->json('footer'));
    }

    public function test_public_404_stays_a_public_html_error_without_admin_bootstrap_or_assets(): void
    {
        Route::get('/legacy-retirement-public-missing', fn () => abort(404));
        add_filter('get_http_exception_view', fn () => 'core/base::errors.404', 999);

        $response = $this->get('/legacy-retirement-public-missing')->assertNotFound()->assertSee('404');
        $html = $response->getContent();

        $this->assertStringNotContainsString('data-page=', $html);
        $this->assertStringNotContainsString('id="app"', $html);
        $this->assertStringNotContainsString('/build/admin/', $html);
        $this->assertStringNotContainsString('/vendor/core/core/base/js/', $html);
        $this->assertStringNotContainsString('/vendor/core/core/base/libraries/jquery', $html);
        $this->assertStringNotContainsString('/vendor/core/core/base/libraries/vue', $html);
    }

    public function test_member_dashboard_and_posts_use_react_without_shared_legacy_assets(): void
    {
        foreach (['blog', 'member', 'custom-field'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        setting()->set(['theme' => 'ripple', 'member_enabled_login' => '1'])->save();
        Theme::setThemeName('ripple');
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        require plugin_path('member/routes/member.php');
        Route::getRoutes()->refreshNameLookups();
        $member = Member::query()->forceCreate([
            'first_name' => 'Public', 'last_name' => 'Member', 'email' => 'public-member@example.test',
            'password' => bcrypt('public-member-password'), 'confirmed_at' => now(),
        ]);
        $this->actingAs($member, 'member');
        foreach (['public.member.dashboard', 'public.member.settings', 'public.member.posts.index', 'public.member.posts.create'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertViewIs('plugins/member::themes.dashboard.react');
            $response->assertSee('data-page=', false)->assertDontSee('jquery', false)->assertDontSee('/vendor/core/', false)->assertDontSee('vue-app', false);
        }
    }

    public function test_public_and_installer_assets_remain_available(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('npm run build:themes', $package['scripts']['build:public']);
        $this->assertStringContainsString('npm run build:assets', $package['scripts']['build:public']);
        $this->assertStringContainsString('npm run build:public', $package['scripts']['build:all']);
        $this->assertFileDoesNotExist(base_path('webpack.mix.js'));

        foreach ([
            'platform/packages/installer/resources/views/welcome.blade.php',
            'platform/plugins/contact/resources/js/contact-public.js',
            'platform/plugins/contact/public/js/contact-public.js',
            'platform/plugins/member/resources/js/dashboard/script.js',
            'platform/themes/ripple/assets/js/ripple.js',
            'platform/themes/ripple/public/js/ripple.js',
            'platform/themes/ripple/public/css/style.css',
            'platform/plugins/custom-field/resources/js/use-custom-fields.js',
            'platform/plugins/custom-field/resources/sass/custom-field.scss',
            'platform/plugins/custom-field/public/js/use-custom-fields.js',
            'platform/plugins/custom-field/public/css/custom-field.css',
        ] as $path) {
            $this->assertFileExists(base_path($path), $path.' must remain part of the public asset/view pipeline.');
        }

        config(['packages.installer.installer.enabled' => true]);
        $this->get('/install/welcome')->assertOk()->assertViewIs('packages/installer::welcome');
    }

    public function test_admin_only_mix_configs_and_legacy_entry_files_are_retired(): void
    {
        foreach ([
            'platform/core/acl/webpack.mix.js',
            'platform/core/dashboard/webpack.mix.js',
            'platform/core/media/webpack.mix.js',
            'platform/core/setting/webpack.mix.js',
            'platform/packages/data-synchronize/webpack.mix.js',
            'platform/packages/get-started/webpack.mix.js',
            'platform/packages/menu/webpack.mix.js',
            'platform/packages/plugin-management/webpack.mix.js',
            'platform/packages/shortcode/webpack.mix.js',
            'platform/packages/widget/webpack.mix.js',
            'platform/plugins/analytics/webpack.mix.js',
            'platform/plugins/audit-log/webpack.mix.js',
            'platform/plugins/backup/webpack.mix.js',
            'platform/plugins/blog/webpack.mix.js',
            'platform/plugins/request-log/webpack.mix.js',
            'platform/plugins/translation/webpack.mix.js',
            'platform/plugins/gallery/resources/js/gallery-admin.js',
            'platform/plugins/gallery/public/js/gallery-admin.js',
            'platform/plugins/member/resources/js/member-admin.js',
            'platform/plugins/member/public/js/member-admin.js',
        ] as $retiredPath) {
            $this->assertFileDoesNotExist(base_path($retiredPath), $retiredPath.' is a retired admin-only entry.');
        }

        foreach ([
            'platform/plugins/language/resources/views/forms/fields/language-switcher.blade.php',
            'platform/plugins/language/resources/views/partials/admin-list-language-chooser.blade.php',
            'platform/plugins/language/resources/views/partials/language-item.blade.php',
            'platform/plugins/language/src/Forms/Fields/LanguageSwitcherField.php',
        ] as $retiredLanguageUi) {
            $this->assertFileDoesNotExist(base_path($retiredLanguageUi), $retiredLanguageUi.' is a retired Blade admin language chooser.');
        }

        $mixConfigs = array_filter(File::allFiles(base_path('platform')), fn ($file) => $file->getFilename() === 'webpack.mix.js');
        $this->assertEmpty($mixConfigs, 'The platform must not use the retired build pipeline.');

        foreach (File::allFiles(base_path('platform')) as $file) {
            if (! preg_match('/(?:^|\/)resources\/js\/admin(?:\.tsx|\/.*\.tsx?)$/', $file->getRelativePathname())) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $this->assertDoesNotMatchRegularExpression('/(?:from\s*[\'"]|import\s*\()[^\'"]*vendor\/core\/[^\'"]*\.(?:js|css)(?:[\'"?]|$)/', $source, $file->getRelativePathname().' must not load legacy Mix assets into the React admin graph.');
        }

        $retiredVendorAreas = 'vendor\/core\/(?:core\/(?:acl|dashboard|media|setting)|packages\/(?:data-synchronize|get-started|menu|plugin-management|shortcode|widget)|plugins\/(?:analytics|audit-log|backup|blog|request-log|translation))\/[^\s\'"<>]+\.(?:js|css)';
        $retiredLegacyEntries = '(?:global-search|cache|system-info|cleanup|notification)\.js|(?:member|gallery)-admin\.js';
        foreach (File::allFiles(base_path('platform')) as $file) {
            if (! in_array($file->getExtension(), ['php', 'blade.php', 'js', 'ts', 'tsx', 'css', 'scss'], true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $path = $file->getRelativePathname();
            if (in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                $this->assertStringNotContainsString('admin-list-language-chooser', $source, $path.' must not reference the retired Blade language chooser.');
            }
            $this->assertDoesNotMatchRegularExpression('/'.$retiredVendorAreas.'/i', $source, $path.' must not directly enqueue retired admin Mix output.');
            $this->assertDoesNotMatchRegularExpression('/'.$retiredLegacyEntries.'/i', $source, $path.' must not reference retired admin-only entry files.');
        }

        $mediaConfig = file_get_contents(base_path('platform/core/media/config/media.php'));
        $this->assertDoesNotMatchRegularExpression('/[\'"\s]libraries[\'"\s]*=>/', $mediaConfig, 'The retired media-library Mix asset key must not remain discoverable.');
    }

    public function test_world_geometry_stays_in_the_lazy_react_chunk_and_is_not_a_public_mix_asset(): void
    {
        $geometryPath = base_path('platform/core/dashboard/resources/js/admin/lib/world-map.json');
        $geometry = json_decode(file_get_contents($geometryPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(900, $geometry['width']);
        $this->assertEqualsWithDelta(440.7063107441331, $geometry['height'], 0.0000001);
        $this->assertCount(176, $geometry['paths']);
        $this->assertSame('United States of America', $geometry['paths']['US']['name']);
        $this->assertNotEmpty($geometry['paths']['US']['path']);

        $visuals = file_get_contents(base_path('platform/core/dashboard/resources/js/admin/Dashboard/analytics-visuals.tsx'));
        $deferred = file_get_contents(base_path('platform/core/dashboard/resources/js/admin/components/deferred-analytics.tsx'));
        $dashboard = file_get_contents(base_path('platform/core/dashboard/resources/js/admin/Dashboard/Index.tsx'));
        $widgetPanel = file_get_contents(base_path('platform/core/dashboard/resources/js/admin/components/widget-panel.tsx'));
        $this->assertStringContainsString('../lib/world-map.json?raw', $visuals);
        $this->assertStringContainsString('import("../Dashboard/analytics-visuals")', $deferred);
        $this->assertStringContainsString('WidgetPanel', $dashboard);
        $this->assertStringContainsString('DeferredAnalytics', $widgetPanel);
        $this->assertStringNotContainsString('import("../Dashboard/analytics-visuals")', $dashboard);

        foreach ([
            'platform/plugins/analytics/public/js/analytics.js',
            'platform/plugins/analytics/public/js/settings.js',
            'platform/plugins/analytics/public/libraries/jvectormap/jquery-jvectormap-1.2.2.css',
            'platform/plugins/analytics/public/libraries/jvectormap/jquery-jvectormap-1.2.2.min.js',
            'platform/plugins/analytics/public/libraries/jvectormap/jquery-jvectormap-world-mill-en.js',
        ] as $retiredAsset) {
            $this->assertFileDoesNotExist(base_path($retiredAsset), $retiredAsset.' must not be published from an analytics public directory.');
        }
    }

    public function test_login_background_defaults_resolve_to_react_assets_and_custom_values_are_preserved(): void
    {
        $backgrounds = config('core.acl.general.backgrounds');
        $this->assertCount(10, $backgrounds);
        foreach ($backgrounds as $background) {
            $this->assertFileExists(base_path($background));
        }

        $default = $this->get(route('access.login'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'Auth/Form');
        $defaultBackground = $default->json('props.admin.appearance.loginBackground');
        $this->assertContains($defaultBackground, $backgrounds);

        setting()->set('login_screen_backgrounds', json_encode(['https://example.test/custom-login-background.jpg']))->save();
        $custom = $this->get(route('access.login'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('props.admin.appearance.loginBackground', 'https://example.test/custom-login-background.jpg');
        $this->assertSame('Auth/Form', $custom->json('component'));

        config(['core.acl.general.backgrounds' => []]);
        setting()->set('login_screen_backgrounds', '[]')->save();
        $disabled = $this->get(route('access.login'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('props.admin.appearance.loginBackground', null);
        $this->assertSame('Auth/Form', $disabled->json('component'));
    }

    public function test_cms_asset_publish_cannot_recreate_retired_admin_vendor_artifacts(): void
    {
        $fixturePublic = sys_get_temp_dir().'/kern-admin-publish-'.uniqid().'/public';
        File::ensureDirectoryExists($fixturePublic);

        $publishGroupsProperty = new \ReflectionProperty(ServiceProvider::class, 'publishGroups');
        $originalPublishGroups = $publishGroupsProperty->getValue();
        $originalPublicPath = public_path();
        $originalBaseHelper = BaseHelper::getFacadeRoot();

        try {
            $this->app->usePublicPath($fixturePublic);

            foreach (['member', 'contact', 'custom-field', 'analytics'] as $plugin) {
                if (! is_plugin_active($plugin)) {
                    $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
                }
            }

            $this->assertFileExists($fixturePublic.'/vendor/core/plugins/analytics/screenshot.png', 'Plugin activation may publish the analytics metadata screenshot.');
            foreach ([
                'vendor/core/plugins/member/js/dashboard/script.js',
                'vendor/core/plugins/contact/js/contact-public.js',
                'vendor/core/plugins/custom-field/js/use-custom-fields.js',
            ] as $activationPublishedAsset) {
                $this->assertFileExists($fixturePublic.'/'.$activationPublishedAsset, $activationPublishedAsset.' must remain publishable when its public plugin is activated.');
            }

            // Plugin activation publishes assets too; start with a clean fixture so
            // the assertions below measure the actual cms:publish:assets run.
            File::cleanDirectory($fixturePublic);

            $currentPublishGroups = $publishGroupsProperty->getValue();
            $currentPublicGroup = $currentPublishGroups['cms-public'] ?? [];
            $remappedPublicGroup = [];
            foreach ($currentPublicGroup as $source => $destination) {
                if (str_starts_with($destination, $fixturePublic.DIRECTORY_SEPARATOR)) {
                    $relativeDestination = substr($destination, strlen($fixturePublic));
                } elseif (str_starts_with($destination, $originalPublicPath.DIRECTORY_SEPARATOR)) {
                    $relativeDestination = substr($destination, strlen($originalPublicPath));
                } else {
                    $relativeDestination = '/other/'.hash('sha256', $destination);
                }
                $remappedPublicGroup[$source] = $fixturePublic.$relativeDestination;
            }
            $currentPublishGroups['cms-public'] = $remappedPublicGroup;
            $publishGroupsProperty->setValue(null, $currentPublishGroups);

            $themeScanStub = \Mockery::mock($originalBaseHelper);
            $themeScanStub->shouldReceive('scanFolder')->once()->with(theme_path())->andReturn([]);
            BaseHelper::swap($themeScanStub);

            $this->artisan('cms:publish:assets')->assertExitCode(0);

            $retiredPrefixes = [
                'vendor/core/core/acl',
                'vendor/core/core/dashboard',
                'vendor/core/core/media',
                'vendor/core/core/setting',
                'vendor/core/packages/data-synchronize',
                'vendor/core/packages/get-started',
                'vendor/core/packages/menu',
                'vendor/core/packages/plugin-management',
                'vendor/core/packages/widget',
                'vendor/core/plugins/audit-log',
                'vendor/core/plugins/backup',
                'vendor/core/plugins/blog',
                'vendor/core/plugins/request-log',
                'vendor/core/plugins/translation',
            ];
            $publishedFiles = collect(File::allFiles($fixturePublic))->map(fn ($file): string => str_replace('\\', '/', substr($file->getPathname(), strlen($fixturePublic) + 1)))->all();
            foreach ($retiredPrefixes as $prefix) {
                $this->assertSame([], array_values(array_filter($publishedFiles, fn (string $path): bool => str_starts_with($path, $prefix.'/'))), $prefix.' must not be recreated by cms:publish:assets.');
            }

            $analyticsFiles = array_values(array_filter($publishedFiles, fn (string $path): bool => str_starts_with($path, 'vendor/core/plugins/analytics/')));
            $this->assertSame(['vendor/core/plugins/analytics/screenshot.png'], $analyticsFiles, 'Only the analytics metadata screenshot may remain publishable.');
            foreach ([
                'vendor/core/plugins/analytics/js/analytics.js',
                'vendor/core/plugins/analytics/js/settings.js',
                'vendor/core/plugins/analytics/libraries/jvectormap/jquery-jvectormap-1.2.2.css',
                'vendor/core/plugins/analytics/libraries/jvectormap/jquery-jvectormap-1.2.2.min.js',
                'vendor/core/plugins/analytics/libraries/jvectormap/jquery-jvectormap-world-mill-en.js',
            ] as $retiredAnalyticsAsset) {
                $this->assertNotContains($retiredAnalyticsAsset, $publishedFiles, $retiredAnalyticsAsset.' must not be recreated by cms:publish:assets.');
            }

            foreach ([
                'vendor/core/core/base/css/core.css',
                'vendor/core/core/table/js/table.js',
                'vendor/core/plugins/member/js/dashboard/script.js',
                'vendor/core/plugins/contact/js/contact-public.js',
                'vendor/core/plugins/custom-field/js/use-custom-fields.js',
                'vendor/core/plugins/analytics/screenshot.png',
                'vendor/core/packages/shortcode/js/front-shortcode.js',
                'vendor/core/packages/shortcode/images/placeholder.jpg',
                'vendor/core/packages/shortcode/images/placeholder-code.jpg',
            ] as $preservedAsset) {
                $this->assertFileExists($fixturePublic.'/'.$preservedAsset, $preservedAsset.' must remain publishable.');
            }

            $shortcodeFiles = array_values(array_filter($publishedFiles, fn (string $path): bool => str_starts_with($path, 'vendor/core/packages/shortcode/')));
            sort($shortcodeFiles);
            $this->assertSame([
                'vendor/core/packages/shortcode/images/placeholder-code.jpg',
                'vendor/core/packages/shortcode/images/placeholder.jpg',
                'vendor/core/packages/shortcode/js/front-shortcode.js',
            ], $shortcodeFiles);
        } finally {
            $this->app->usePublicPath($originalPublicPath);
            $publishGroupsProperty->setValue(null, $originalPublishGroups);
            BaseHelper::swap($originalBaseHelper);
            File::deleteDirectory(dirname($fixturePublic));
        }
    }

    private function assertInertiaShell(string $html): void
    {
        $this->assertStringContainsString('id="app"', $html);
        $this->assertStringContainsString('data-page=', $html);
        $shell = file_get_contents(base_path('platform/core/base/resources/views/admin/app.blade.php'));
        $this->assertStringContainsString("withEntryPoints(['resources/js/admin.tsx'])", $shell);
        $this->assertStringContainsString('@inertia', $shell);
        $this->assertDoesNotMatchRegularExpression('/(?:src|href)=[\'"][^\'"]*\/vendor\/core\/[^\'"]+\.(?:js|css)/i', $html, 'React admin shells must not load shared legacy Mix assets.');

        foreach ([
            '/vendor/core/core/base/js/admin.js',
            '/vendor/core/core/base/js/vue-app.js',
            '/vendor/core/core/base/libraries/vue.global',
            '/vendor/core/core/base/libraries/jquery.min.js',
            '/vendor/core/plugins/member/js/member-admin.js',
            '/vendor/core/plugins/gallery/js/gallery-admin.js',
        ] as $legacyUrl) {
            $this->assertStringNotContainsString($legacyUrl, $html, 'The React admin shell must not emit retired bundle URL '.$legacyUrl.'.');
        }
    }

    private function assertPublicMemberHtml(string $html): void
    {
        $this->assertStringContainsString('<html', $html);
        $this->assertStringNotContainsString('data-page=', $html);
        $this->assertStringNotContainsString('/build/admin/', $html);
    }
}
