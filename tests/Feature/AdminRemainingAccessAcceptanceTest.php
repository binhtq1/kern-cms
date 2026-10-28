<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Facades\Theme;
use Kern\Widget\Widgets\Text;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingAccessAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $originalStorage;

    private string $originalPublic;

    private string $originalLang;

    private string $originalBase;

    private string $originalBootstrap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsesTestingDatabase();
        $this->originalStorage = storage_path();
        $this->originalPublic = public_path();
        $this->originalLang = lang_path();
        $this->fixtureRoot = sys_get_temp_dir().'/kern-content-acceptance-'.bin2hex(random_bytes(8));
        foreach (['storage/framework/views', 'storage/framework/cache', 'storage/logs', 'public/build', 'lang/en', 'lang/vi'] as $folder) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$folder);
        }
        File::copyDirectory($this->originalPublic.'/build/admin', $this->fixtureRoot.'/public/build/admin');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config(['packages.installer.installer.enabled' => false, 'view.compiled' => storage_path('framework/views')]);
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->originalBase = base_path();
        $this->originalBootstrap = $this->app->bootstrapPath();
        foreach (['platform/plugins/access-fixture', 'platform/themes/access-fixture/public/css', 'bootstrap/cache', 'config', 'resources'] as $folder) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$folder);
        }
        File::copy($this->originalBase.'/composer.json', $this->fixtureRoot.'/composer.json');
        $this->app->setBasePath($this->fixtureRoot);
        $this->app->useBootstrapPath($this->fixtureRoot.'/bootstrap');
        File::put(theme_path('access-fixture/public/css/style.integration.css'), 'original CSS');
        File::put(public_path('robots.txt'), 'User-agent: *');
        File::put(plugin_path('access-fixture/plugin.json'), json_encode(['id' => 'access-fixture', 'name' => 'Access fixture', 'namespace' => 'Acceptance\\Fixture\\', 'provider' => 'Acceptance\\Fixture\\Provider', 'version' => '1.0.0']));
        setting()->set(['theme' => 'access-fixture', 'custom_header_js' => 'original JS', 'custom_header_html' => '<p>Original HTML</p>'])->save();
        Theme::setThemeName('access-fixture');
        File::ensureDirectoryExists(dirname(Theme::getStyleIntegrationPath()));
        File::put(Theme::getStyleIntegrationPath(), 'original public CSS');
        config(['packages.theme.general.enable_custom_js' => true, 'packages.theme.general.enable_custom_html' => true, 'packages.theme.general.enable_robots_txt_editor' => true]);
        Storage::disk('local')->put('data-synchronize/access.csv', "name,status\nAllowed payload,published\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot)) {
            $this->app->useStoragePath($this->originalStorage);
            $this->app->usePublicPath($this->originalPublic);
            $this->app->useLangPath($this->originalLang);
            $this->app->setBasePath($this->originalBase);
            $this->app->useBootstrapPath($this->originalBootstrap);
            File::deleteDirectory($this->fixtureRoot);
        }
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function payload(string $kind): array
    {
        return match ($kind) {
            'posts' => ['name' => 'Access story', 'status' => 'published', 'tag' => '[]'],
            'menus' => ['name' => 'Access menu', 'status' => 'published', 'menu_nodes' => '[]', 'locations' => []],
            'widgets' => ['groups' => [['sidebar_id' => 'primary_sidebar', 'items' => []]]],
            'media' => ['name' => 'Access folder', 'parent_id' => 0],
            'theme' => ['custom_css' => 'body { color: red; }'],
            'plugins' => ['name' => 'access-fixture'],
            'languages' => ['lang_id' => 1],
            'system' => ['type' => 'clear_cms_cache'],
            'tools' => ['file_name' => 'access.csv', 'offset' => 0, 'limit' => 50],
            'logs' => ['ids' => [1]],
            'api' => ['name' => 'Access token'],
        };
    }

    private function snapshot(): array
    {
        $tables = ['posts', 'categories', 'tags', 'blocks', 'galleries', 'members', 'contact_custom_fields', 'menus', 'menu_nodes', 'widgets', 'media_files', 'media_folders', 'settings', 'personal_access_tokens'];
        $data = [];
        foreach ($tables as $table) {
            $data[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        foreach (['public', 'local'] as $disk) {
            $files = Storage::disk($disk)->allFiles();
            sort($files);
            foreach ($files as $file) {
                $data['files'][$disk][$file] = hash('sha256', Storage::disk($disk)->get($file));
            }
        }
        $data['public_css'] = File::get(Theme::getStyleIntegrationPath());
        $data['css'] = File::get(theme_path('access-fixture/public/css/style.integration.css'));
        $data['robots'] = File::get(public_path('robots.txt'));
        $data['plugin'] = File::get(plugin_path('access-fixture/plugin.json'));
        $data['csv'] = Storage::disk('local')->get('data-synchronize/access.csv');

        return $data;
    }

    #[TestWith(['posts', 'posts.index', 'posts.store', 'post'])]
    #[TestWith(['menus', 'menus.index', 'menus.store', 'post'])]
    #[TestWith(['widgets', 'widgets.index', 'widgets.save_all', 'post'])]
    #[TestWith(['media', 'media.index', 'media.folders.create', 'post'])]
    #[TestWith(['theme', 'theme.index', 'theme.custom-css.post', 'post'])]
    #[TestWith(['plugins', 'plugins.index', 'plugins.change.status', 'put'])]
    #[TestWith(['languages', 'languages.index', 'languages.set.default.update', 'post'])]
    #[TestWith(['system', 'system.index', 'system.cache.clear', 'post'])]
    #[TestWith(['tools', 'tools.data-synchronize', 'tools.data-synchronize.import.posts.validate', 'post'])]
    #[TestWith(['logs', 'audit-log.index', 'audit-log.bulk-destroy', 'delete'])]
    #[TestWith(['api', 'api.settings', 'api.sanctum-token.store', 'post'])]
    public function test_guest_cannot_read_or_mutate_each_remaining_admin_group(string $kind, string $read, string $write, string $method): void
    {
        $before = $this->snapshot();
        $this->get(route($read), $this->headers())->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
        $this->json(strtoupper($method), route($write), $this->payload($kind))->assertUnauthorized();
        if ($kind === 'languages') {
            $this->get(route('translations.index'), $this->headers())->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
            $this->postJson(route('translations.react.import', ['ref_lang' => 'vi']), ['rows' => []])->assertUnauthorized();
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertGuest();
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_restricted_widget_layout_and_configuration_save_cannot_change_existing_widgets(bool $all): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $item = ['id' => Text::class, 'name' => 'Original widget', 'content' => '<p>Original widget body</p>'];
        $payload = ['sidebar_id' => 'primary_sidebar', 'items' => [$item]];
        $this->postJson(route('widgets.save_widgets_sidebar'), $payload)->assertOk()->assertJsonPath('error', false);
        $before = $this->snapshot();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.index' => true]]));
        $changed = [...$payload, 'items' => [[...$item, 'content' => '<p>Injected widget body</p>']]];
        $this->postJson(route($all ? 'widgets.save_all' : 'widgets.save_widgets_sidebar'), $all ? ['groups' => [$changed]] : $changed)->assertUnauthorized();
        $this->assertSame($before, $this->snapshot());
    }

    #[TestWith(['theme.custom-css.post', 'custom_css', 'body { color: red; }'])]
    #[TestWith(['theme.custom-js.post', 'custom_header_js', 'window.injected = true;'])]
    #[TestWith(['theme.custom-html.post', 'custom_header_html', '<p>Injected HTML</p>'])]
    #[TestWith(['theme.robots-txt.post', 'robots_txt_content', 'User-agent: *\nDisallow: /private'])]
    public function test_restricted_theme_editor_and_asset_saves_leave_files_and_settings_unchanged(string $route, string $field, string $value): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['theme.index' => true]]));
        $before = $this->snapshot();
        $this->postJson(route($route), [$field => $value])->assertUnauthorized();
        $this->assertSame($before, $this->snapshot());
    }

    #[TestWith(['tools.data-synchronize.import.posts.validate'])]
    #[TestWith(['tools.data-synchronize.import.posts.store'])]
    #[TestWith(['tools.data-synchronize.export.posts.store'])]
    public function test_restricted_data_validate_import_and_export_have_no_file_or_database_effects(string $route): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['tools.data-synchronize' => true, 'posts.index' => true]]));
        $before = $this->snapshot();
        $payload = str_contains($route, '.export.') ? ['format' => 'csv', 'columns' => ['name', 'status']] : $this->payload('tools');
        $this->postJson(route($route), $payload)->assertUnauthorized();
        $this->assertSame($before, $this->snapshot());
    }

    #[TestWith(['tags', 'tags', 'tags'])]
    #[TestWith(['block', 'blocks', 'block'])]
    #[TestWith(['galleries', 'galleries', 'galleries'])]
    #[TestWith(['member', 'members', 'member'])]
    #[TestWith(['contacts.custom-fields', 'contact_custom_fields', 'contacts.custom-fields'])]
    public function test_read_only_content_actor_cannot_create_update_or_delete_valid_resource(string $route, string $table, string $permission): void
    {
        $payload = match ($table) {
            'tags' => ['name' => 'Original tag', 'status' => 'published'],
            'blocks' => ['name' => 'Original block', 'alias' => 'access-block', 'description' => 'Original summary', 'content' => '<p>Original block body</p>', 'status' => 'published'],
            'galleries' => ['name' => 'Original gallery', 'description' => 'Original gallery summary', 'order' => 0, 'status' => 'published', 'gallery' => '[]'],
            'members' => ['first_name' => 'Original', 'last_name' => 'Member', 'email' => 'original@example.test', 'password' => 'acceptance-password', 'password_confirmation' => 'acceptance-password'],
            'contact_custom_fields' => ['name' => 'Original field', 'type' => 'text', 'required' => 0, 'order' => 0, 'status' => 'published', 'options' => []],
        };
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->postJson(route($route.'.store'), $payload)->assertOk()->assertJsonPath('error', false);
        $id = DB::table($table)->sole()->id;
        $this->actingAs(UserFactory::new()->create(['permissions' => [$permission.'.index' => true]]));
        $before = $this->snapshot();
        $new = [...$payload, 'name' => 'Injected resource', 'first_name' => 'Injected', 'email' => 'injected@example.test', 'alias' => 'injected-access-block'];
        $this->postJson(route($route.'.store'), $new)->assertUnauthorized();
        $this->postJson(route($route.'.update', $id), $new)->assertUnauthorized();
        $this->deleteJson(route($route.'.destroy', $id))->assertUnauthorized();
        $this->assertSame($before, $this->snapshot());
    }
}
