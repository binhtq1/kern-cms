<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\Base\Supports\AdminContentResource;
use Kern\CustomField\Models\FieldGroup;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Language\Providers\LanguageServiceProvider;
use Kern\Media\Models\MediaFile;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Setting\Facades\Setting;
use Kern\Slug\Models\Slug;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Kern\Translation\Manager;
use Kern\Widget\Facades\WidgetGroup;
use Kern\Widget\Models\Widget;
use Kern\Widget\Widgets\Text;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminGlobalRollbackAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.general.google_fonts_enabled' => false]);
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function legacy(bool $enabled): void
    {
        config(['core.base.admin.legacy_mode' => $enabled]);
        $this->assertSame($enabled, config('core.base.admin.legacy_mode'));
    }

    #[TestWith(['dashboard.index', 'Dashboard/Index'])]
    #[TestWith(['settings.index', 'Settings/Index'])]
    #[TestWith(['settings.general', 'Settings/Form'])]
    #[TestWith(['system.index', 'System/Index'])]
    #[TestWith(['tools.data-synchronize', 'Operations/Index'])]
    public function test_global_flag_does_not_switch_authenticated_entry_routes_away_from_react(string $name, string $component): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $url = route($name);
        $this->legacy(false);
        $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', $component);
        $this->legacy(true);
        $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', $component);
        $this->legacy(false);
        $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', $component);
    }

    public function test_all_installed_plugins_and_ripple_keep_registered_admin_screens_inertia_with_global_rollback(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->app->register(LanguageServiceProvider::class, true);
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $screens = [
            'dashboard.index' => 'Dashboard/Index',
            'pages.index' => 'Pages/Index', 'pages.create' => 'Pages/Form',
            'custom-fields.index' => 'FieldGroups/Index', 'custom-fields.create' => 'FieldGroups/Form',
            'menus.index' => 'Menu/Index', 'menus.create' => 'Menu/Form',
            'widgets.index' => 'Widgets/Index', 'media.index' => 'Media/Index',
            'settings.index' => 'Settings/Index', 'system.index' => 'System/Index',
            'system.info' => 'System/Information', 'system.cronjob' => 'System/Cronjob',
            'system.cleanup' => 'System/Cleanup', 'plugins.index' => 'System/Plugins',
            'tools.data-synchronize' => 'Operations/Index',
            'get-started.index' => 'System/Setup', 'core.global-search' => 'System/Search',
            'theme.index' => 'Theme/Index', 'theme.options' => 'Theme/Options',
            'theme.custom-css' => 'Theme/Editor', 'theme.custom-js' => 'Theme/Editor',
            'theme.custom-html' => 'Theme/Editor', 'theme.robots-txt' => 'Theme/Editor',
            'languages.index' => 'Languages/Index', 'translations.index' => 'Translations/Index',
            'translations.locales' => 'Locales/Index',
            'users.index' => 'ACL/Index', 'users.create' => 'ACL/UserForm',
            'roles.index' => 'ACL/Index', 'roles.create' => 'ACL/RoleForm',
        ];
        foreach (['settings.general', 'settings.admin-appearance', 'settings.cache', 'settings.datatables', 'settings.email', 'settings.email.rules', 'settings.email.template', 'settings.media', 'settings.website-tracking', 'optimize.settings', 'api.settings', 'blog.settings', 'contact.settings', 'member.settings', 'slug.settings', 'captcha.settings', 'social-login.settings', 'analytics.settings'] as $name) {
            $screens[$name] = 'Settings/Form';
        }
        foreach (['system.cache', 'backups.index', 'audit-log.index', 'request-log.index', 'api.sanctum-token.index', 'notifications.index'] as $name) {
            $screens[$name] = 'System/Console';
        }
        $parameters = [];
        foreach (app(AdminContentResource::class)->modules() as $module) {
            $screens[$module['route'].'.index'] = $module['screen'].'/Index';
            if ($module['route'] !== 'contacts') {
                $screens[$module['route'].'.create'] = $module['screen'].'/Form';
            }
            $attributes = $module['route'] === 'member'
                ? ['first_name' => 'Matrix', 'last_name' => 'Member', 'email' => 'matrix@example.test', 'password' => bcrypt('local-fixture-password')]
                : ['name' => 'Matrix fixture'];
            if (in_array($module['route'], ['posts', 'categories', 'tags'], true)) {
                $attributes['author_id'] = auth()->id();
                $attributes['author_type'] = User::class;
            }
            if ($module['route'] === 'galleries') {
                $attributes['description'] = 'Matrix gallery';
            }
            if ($module['route'] === 'contacts') {
                $attributes['email'] = 'matrix-contact@example.test';
                $attributes['content'] = 'Matrix contact';
            }
            if ($module['route'] === 'block') {
                $attributes['alias'] = 'matrix-fixture';
            }
            if ($module['route'] === 'contacts.custom-fields') {
                $attributes['type'] = 'text';
            }
            $record = $module['class']::query()->create($attributes);
            $screens[$module['route'].'.edit'] = $module['screen'].'/Form';
            $parameters[$module['route'].'.edit'] = $record;
        }
        $page = PageFactory::new()->create();
        $menu = MenuModel::query()->create(['name' => 'Matrix menu', 'status' => 'published']);
        $role = Role::query()->create(['name' => 'Matrix role', 'permissions' => [], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        $group = FieldGroup::query()->create(['title' => 'Matrix fields', 'status' => 'published', 'rules' => '[]']);
        foreach (['pages.edit' => ['Pages/Form', $page], 'menus.edit' => ['Menu/Form', $menu], 'users.profile.view' => ['ACL/UserForm', auth()->user()], 'roles.edit' => ['ACL/RoleForm', $role], 'custom-fields.edit' => ['FieldGroups/Form', $group]] as $name => [$component, $record]) {
            $screens[$name] = $component;
            $parameters[$name] = $record;
        }
        $unexpected = [];
        foreach ($screens as $name => $component) {
            $url = route($name, $parameters[$name] ?? []);
            $this->legacy(false);
            $response = $this->get($url, $this->headers());
            if ($response->status() !== 200) {
                $fallback = $this->get($url);
                $unexpected[$name] = ['status' => $response->status(), 'reason' => $fallback->headers->get('X-Admin-Renderer-Reason')];

                continue;
            }
            $response->assertJsonPath('component', $component)->assertHeaderMissing('X-Admin-Renderer-Reason');
            $this->legacy(true);
            $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', $component);
        }
        $this->assertSame([], $unexpected, json_encode($unexpected));
    }

    public function test_permalink_http_language_chooser_preserves_locale_prefix_scope_and_unsupported_extensions_are_rejected(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (['language', 'language-advanced'] as $plugin) {
            $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
        }
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $baseKey = 'permalink-kern-page-models-page';
        setting()->set([$baseKey => 'english-pages', $baseKey.'-vi' => 'trang-cu'])->save();
        $page = PageFactory::new()->create();
        $slug = Slug::query()->create(['key' => 'unchanged-page', 'prefix' => 'english-pages', 'reference_type' => Page::class, 'reference_id' => $page->id]);

        $default = $this->get(route('slug.settings'), $this->headers())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $defaultFields = collect($default->json('props.fields'))->keyBy('name');
        $this->assertSame('english-pages', $defaultFields[$baseKey]['value']);
        $this->assertSame('', $defaultFields['ref_lang']['value']);
        $default->assertJsonPath('props.languageNavigation.0.active', true)->assertJsonPath('props.languageNavigation.1.url', route('slug.settings', ['ref_lang' => 'vi']));
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        $vi = $this->get(route('slug.settings', ['ref_lang' => 'vi']), $this->headers())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $viFields = collect($vi->json('props.fields'))->keyBy('name');
        $this->assertSame('trang-cu', $viFields[$baseKey.'-vi']['value']);
        $this->assertSame('vi', $viFields['ref_lang']['value']);
        $vi->assertJsonPath('props.languageNavigation.1.active', true);

        $this->withSession(['_token' => 'permalink-acceptance-token']);
        $this->put(route('slug.settings.update'), ['_token' => 'permalink-acceptance-token', $baseKey.'-vi' => 'trang-moi', $baseKey.'-vi-model-key' => Page::class, 'ref_lang' => 'vi'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('trang-moi', setting($baseKey.'-vi'));
        $this->assertSame('english-pages', setting($baseKey));
        $this->assertSame('english-pages', $slug->fresh()->prefix);
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        $reopened = $this->get(route('slug.settings', ['ref_lang' => 'vi']), $this->headers())->assertOk();
        $this->assertSame('trang-moi', collect($reopened->json('props.fields'))->firstWhere('name', $baseKey.'-vi')['value']);
        add_filter('setting_permalink_meta_boxes', fn (?string $html): string => $html.'<section>Unknown extension controls</section>', 2000);
        $this->get(route('slug.settings'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_global_flag_keeps_guest_authentication_on_react(): void
    {
        $url = route('access.login');
        $this->legacy(false);
        $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', 'Auth/Form');
        $this->legacy(true);
        $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', 'Auth/Form');
        $this->legacy(false);
        $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', 'Auth/Form');
    }

    public function test_menu_writes_and_reads_use_react_and_public_render_same_tree(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        Menu::addMenuLocation('rollback-fixture', 'Rollback fixture');
        Setting::set('cache_front_menu_enabled', false)->save();
        $tree = [['menuItem' => ['title' => 'Blade parent', 'url' => '/blade-parent'], 'children' => [['menuItem' => ['title' => 'Blade child', 'url' => '/blade-child', 'target' => '_blank']]]]];
        $payload = ['name' => 'Shared rollback menu', 'status' => 'published', 'locations' => ['rollback-fixture'], 'menu_nodes' => json_encode($tree)];
        $this->legacy(true);
        $this->get(route('menus.create'), $this->headers())->assertOk()->assertJsonPath('component', 'Menu/Form');
        $this->post(route('menus.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $menu = MenuModel::query()->sole();
        $this->legacy(false);
        $response = $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.nodes.0.title', 'Blade parent')->assertJsonPath('props.menu.nodes.1.title', 'Blade child');
        $this->assertSame($response->json('props.menu.nodes.0.id'), $response->json('props.menu.nodes.1.parent'));
        $parent = $menu->menuNodes()->where('title', 'Blade parent')->sole();
        $child = $menu->menuNodes()->where('title', 'Blade child')->sole();
        $tree[0]['menuItem'] = ['id' => $parent->id, 'title' => 'React parent', 'url' => '/react-parent'];
        $tree[0]['children'][0]['menuItem'] = ['id' => $child->id, 'title' => 'React child', 'url' => '/react-child', 'target' => '_blank'];
        $this->post(route('menus.update', $menu), [...$payload, 'menu_nodes' => json_encode($tree)], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(true);
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.nodes.0.title', 'React parent')->assertJsonPath('props.menu.nodes.1.title', 'React child');
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame(['rollback-fixture'], $menu->locations()->pluck('location')->all());
        Menu::load(true);
        $html = Menu::renderMenuLocation('rollback-fixture');
        $this->assertStringContainsString('React parent', $html);
        $this->assertStringContainsString('/react-child', $html);
        $this->assertSame(2, $menu->menuNodes()->count());
        $tree[0]['menuItem']['title'] = 'Blade re-edited parent';
        $this->post(route('menus.update', $menu), [...$payload, 'menu_nodes' => json_encode($tree)], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(false);
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.nodes.0.title', 'Blade re-edited parent')->assertJsonPath('props.menu.nodes.1.title', 'React child');
        $this->assertSame(2, $menu->menuNodes()->count());
    }

    public function test_widgets_keep_shared_configuration_and_public_output_across_global_rollback(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        register_sidebar(['id' => 'rollback-footer', 'name' => 'Rollback footer']);
        $this->legacy(true);
        $this->get(route('widgets.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Widgets/Index');
        $this->postJson(route('widgets.save_widgets_sidebar'), ['sidebar_id' => 'primary_sidebar', 'items' => [http_build_query(['id' => Text::class, 'name' => 'Blade widget', 'content' => '<p>Blade shared content</p>'])]])->assertOk()->assertJsonPath('error', false);
        $this->legacy(false);
        $response = $this->get(route('widgets.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Widgets/Index');
        $primary = collect($response->json('props.groups'))->firstWhere('id', 'primary_sidebar');
        $this->assertSame('<p>Blade shared content</p>', $primary['items'][0]['data']['content']);
        $this->post(route('widgets.save_all'), ['groups' => [['sidebar_id' => 'primary_sidebar', 'items' => []], ['sidebar_id' => 'rollback-footer', 'items' => [['id' => Text::class, 'name' => 'React widget', 'content' => '<p>React shared content</p>']]]]], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(true);
        $this->get(route('widgets.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Widgets/Index');
        $this->assertDatabaseMissing('widgets', ['sidebar_id' => 'primary_sidebar']);
        $widget = Widget::query()->sole();
        $this->assertSame('rollback-footer', $widget->sidebar_id);
        $this->assertSame(0, $widget->position);
        WidgetGroup::load(true);
        $this->assertStringContainsString('React shared content', WidgetGroup::render('rollback-footer'));
        $this->postJson(route('widgets.save_widgets_sidebar'), ['sidebar_id' => 'rollback-footer', 'items' => [http_build_query(['id' => Text::class, 'name' => 'Blade re-edited widget', 'content' => '<p>Blade re-edited content</p>'])]])->assertOk()->assertJsonPath('error', false);
        $this->legacy(false);
        $response = $this->get(route('widgets.index'), $this->headers())->assertOk();
        $footer = collect($response->json('props.groups'))->firstWhere('id', 'rollback-footer');
        $this->assertSame('<p>Blade re-edited content</p>', $footer['items'][0]['data']['content']);
        $this->assertDatabaseCount('widgets', 1);
    }

    public function test_general_setting_values_roundtrip_without_module_flags(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $payload = ['admin_email' => ['rollback@example.test'], 'time_zone' => 'Asia/Ho_Chi_Minh', 'enable_send_error_reporting_via_email' => 0];
        $this->legacy(true);
        $this->get(route('settings.general'), $this->headers())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $this->put(route('settings.general.update'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(false);
        $response = $this->get(route('settings.general'), $this->headers())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $this->assertSame('Asia/Ho_Chi_Minh', $fields['time_zone']['value']);
        $this->put(route('settings.general.update'), [...$payload, 'time_zone' => 'Europe/Paris'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(true);
        $this->get(route('settings.general'), $this->headers())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $this->assertSame('Europe/Paris', setting('time_zone'));
        $this->assertSame(['rollback@example.test'], json_decode(setting('admin_email'), true));
        $this->put(route('settings.general.update'), [...$payload, 'time_zone' => 'UTC'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(false);
        $response = $this->get(route('settings.general'), $this->headers())->assertOk();
        $this->assertSame('UTC', collect($response->json('props.fields'))->firstWhere('name', 'time_zone')['value']);
    }

    public function test_media_rename_and_alt_roundtrip_with_real_blade_shell_and_shared_list_endpoint(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        $file = MediaFile::query()->create(['name' => 'Rollback image', 'url' => 'rollback/image.png', 'mime_type' => 'image/png', 'visibility' => 'public', 'user_id' => $actor->id, 'size' => 10, 'folder_id' => 0]);
        $selected = ['id' => (string) $file->id, 'is_folder' => false];
        $this->legacy(true);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Media/Index');
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selected, 'name' => 'Blade name']]])->assertOk()->assertJsonPath('error', false);
        $this->postJson(route('media.global_actions'), ['action' => 'alt_text', 'selected' => [[...$selected, 'alt' => 'Blade description']]])->assertOk()->assertJsonPath('error', false);
        $this->legacy(false);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Media/Index');
        $this->getJson(route('media.list', ['view_in' => 'all_media']))->assertOk()->assertJsonPath('data.files.0.name', 'Blade name')->assertJsonPath('data.files.0.alt', 'Blade description');
        foreach (['rename' => ['name' => 'React name'], 'alt_text' => ['alt' => 'React description']] as $action => $value) {
            $this->post(route('media.global_actions'), ['action' => $action, 'selected' => [[...$selected, ...$value]]], [...$this->headers(), 'Accept' => 'application/json'])->assertOk()->assertJsonPath('error', false);
        }
        $this->legacy(true);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Media/Index');
        $this->getJson(route('media.list', ['view_in' => 'all_media']))->assertOk()->assertJsonPath('data.files.0.name', 'React name')->assertJsonPath('data.files.0.alt', 'React description');
        $this->assertDatabaseHas('media_files', ['id' => $file->id, 'name' => 'React name', 'alt' => 'React description', 'url' => 'rollback/image.png']);
        $this->postJson(route('media.global_actions'), ['action' => 'alt_text', 'selected' => [[...$selected, 'alt' => 'Blade re-edited description']]])->assertOk()->assertJsonPath('error', false);
        $this->legacy(false);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Media/Index');
        $this->getJson(route('media.list', ['view_in' => 'all_media']))->assertOk()->assertJsonPath('data.files.0.name', 'React name')->assertJsonPath('data.files.0.alt', 'Blade re-edited description');
        $this->assertDatabaseCount('media_files', 1);
    }

    public function test_languages_share_same_record_between_blade_and_react(): void
    {
        app(PluginService::class)->activate('language');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $payload = ['lang_name' => 'Rollback English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_rtl' => false, 'lang_order' => 1];
        $this->legacy(true);
        $this->get(route('languages.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Languages/Index');
        $this->post(route('languages.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $language = Language::query()->sole();
        $this->legacy(false);
        $this->get(route('languages.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Languages/Index')->assertJsonPath('props.languages.0.lang_name', 'Rollback English');
        $this->post(route('languages.edit'), [...$payload, 'lang_id' => $language->getKey(), 'lang_name' => 'React English'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(true);
        $this->get(route('languages.index'), $this->headers())->assertOk()->assertJsonPath('props.languages.0.lang_name', 'React English');
        $this->assertSame('React English', $language->fresh()->lang_name);
        $this->assertTrue($language->fresh()->lang_is_default);
        $this->assertDatabaseCount('languages', 1);
        $this->post(route('languages.edit'), [...$payload, 'lang_id' => $language->getKey(), 'lang_name' => 'Blade re-edited English'])->assertRedirect()->assertSessionHasNoErrors();
        $this->legacy(false);
        $this->get(route('languages.index'), $this->headers())->assertOk()->assertJsonPath('props.languages.0.lang_name', 'Blade re-edited English');
        $this->assertDatabaseCount('languages', 1);
    }

    public function test_theme_locale_uses_isolated_override_file_across_global_rollback(): void
    {
        app(PluginService::class)->activate('translation');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $originalLangPath = lang_path();
        $temporary = storage_path('framework/testing/global-rollback-locales-'.uniqid());
        foreach (['en', 'vi', 'vendor/themes/ripple'] as $folder) {
            File::ensureDirectoryExists($temporary.'/'.$folder);
        }
        $this->app->useLangPath($temporary);
        Theme::setThemeName('ripple');
        try {
            $manager = app(Manager::class);
            $key = array_key_first($manager->getThemeTranslations('en'));
            $this->assertNotNull($key);
            $url = route('translations.theme-translations', ['ref_lang' => 'vi']);
            $payload = ['pk' => 'vi', 'name' => $key, 'value' => 'Blade locale value'];
            $this->legacy(true);
            $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', 'Translations/Index');
            $this->post(route('translations.theme-translations.post'), $payload)->assertRedirect()->assertSessionHasNoErrors();
            $this->legacy(false);
            $this->get($url.'&search=Blade%20locale%20value', $this->headers())->assertOk()->assertJsonPath('component', 'Translations/Index')->assertJsonPath('props.items.data.0.value', 'Blade locale value');
            $this->post(route('translations.theme-translations.post'), [...$payload, 'value' => 'React locale value'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
            $this->legacy(true);
            $this->get($url, $this->headers())->assertOk()->assertJsonPath('component', 'Translations/Index');
            $this->post($url, ['draw' => 1, 'start' => 0, 'length' => 100], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('React locale value');
            $this->assertSame('React locale value', $manager->getThemeTranslations('vi')[$key]);
            $this->assertSame('React locale value', json_decode(File::get($temporary.'/vendor/themes/ripple/vi.json'), true)[$key]);
            $this->post(route('translations.theme-translations.post'), [...$payload, 'value' => 'Blade re-edited locale'])->assertRedirect()->assertSessionHasNoErrors();
            $this->legacy(false);
            $this->get($url.'&search=Blade%20re-edited%20locale', $this->headers())->assertOk()->assertJsonPath('props.items.data.0.value', 'Blade re-edited locale');
        } finally {
            $this->app->useLangPath($originalLangPath);
            File::deleteDirectory($temporary);
        }
    }
}
