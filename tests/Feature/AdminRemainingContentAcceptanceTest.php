<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Block\Models\Block;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\CustomField\Models\CustomField;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Language\Facades\Language;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language as LanguageModel;
use Kern\Language\Providers\HookServiceProvider;
use Kern\Language\Providers\LanguageServiceProvider;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Menu\Models\MenuNode;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingContentAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $originalStorage;

    private string $originalPublic;

    private string $originalLang;

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
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot)) {
            $this->app->useStoragePath($this->originalStorage);
            $this->app->usePublicPath($this->originalPublic);
            $this->app->useLangPath($this->originalLang);
            File::deleteDirectory($this->fixtureRoot);
        }
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function publicTheme(): void
    {
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_page_template_change_preserves_all_omitted_metadata_and_content(): void
    {
        $this->publicTheme();
        $group = FieldGroup::query()->create(['title' => 'Template preservation', 'status' => 'published', 'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Page::class]]])]);
        $field = FieldItem::query()->create(['field_group_id' => $group->id, 'title' => 'Preserved field', 'slug' => 'preserved_field', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        $images = [['img' => 'https://example.test/preserved.jpg', 'description' => 'Preserved caption']];
        $seo = ['seo_title' => 'Preserved SEO title', 'seo_description' => 'Preserved SEO description', 'index' => 'noindex'];
        $this->post(route('pages.store'), ['name' => 'Template preservation page', 'status' => 'published', 'content' => '<p>Preserved content</p>', 'template' => 'default', 'slug' => 'template-preservation', 'is_slug_editable' => 1, 'seo_meta' => $seo, 'gallery' => json_encode($images), 'banner_image' => 'https://example.test/banner.jpg'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $page = Page::query()->where('name', 'Template preservation page')->sole();
        $groups = $this->get(route('pages.edit', $page), $this->headers())->assertOk()->json('props.customFieldGroups');
        $groups[0]['items'][0]['value'] = 'Preserved metadata value';
        $this->post(route('pages.update', $page), ['name' => $page->name, 'status' => 'published', 'custom_fields' => json_encode($groups)], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $metadata = $page->metadata()->orderBy('meta_key')->get(['meta_key', 'meta_value'])->toArray();
        $gallery = GalleryMeta::query()->where('reference_type', Page::class)->where('reference_id', $page->id)->sole()->images;
        $custom = CustomField::query()->where('use_for_id', $page->id)->where('field_item_id', $field->id)->sole()->getRawOriginal('value');
        $this->post(route('pages.update', $page), ['name' => $page->name, 'status' => 'published', 'template' => 'no-sidebar'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('no-sidebar', $page->fresh()->template);
        $this->assertSame('<p>Preserved content</p>', $page->fresh()->content);
        $this->assertSame($metadata, $page->fresh()->metadata()->orderBy('meta_key')->get(['meta_key', 'meta_value'])->toArray());
        $this->assertSame($gallery, GalleryMeta::query()->where('reference_type', Page::class)->where('reference_id', $page->id)->sole()->images);
        $this->assertSame($custom, CustomField::query()->where('use_for_id', $page->id)->where('field_item_id', $field->id)->sole()->getRawOriginal('value'));
        $this->get(route('pages.edit', $page), $this->headers())->assertOk()->assertJsonPath('props.page.template', 'no-sidebar')->assertJsonPath('props.page.seo_meta.seo_title', $seo['seo_title'])->assertJsonPath('props.page.banner_image', 'https://example.test/banner.jpg')->assertJsonPath('props.gallery.0.description', 'Preserved caption')->assertJsonPath('props.customFieldGroups.0.items.0.value', 'Preserved metadata value');
    }

    #[TestWith([null])]
    #[TestWith(['video'])]
    public function test_single_registered_post_format_is_hidden_and_omitted_format_is_preserved_in_public_content(?string $legacyFormat): void
    {
        $this->publicTheme();
        $this->assertCount(1, get_post_formats(true));
        $response = $this->get(route('posts.create'), $this->headers())->assertOk();
        $this->assertNotContains('format_type', array_column($response->json('props.fields'), 'name'));
        $this->post(route('posts.store'), ['name' => 'Default format article', 'status' => 'published', 'content' => '<p>Default article body</p>', 'slug' => 'default-format-article', 'is_slug_editable' => 1], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $post = Post::query()->where('name', 'Default format article')->sole();
        $this->assertContains($post->format_type, [null, '']);
        if ($legacyFormat !== null) {
            $post->update(['format_type' => $legacyFormat]);
        }
        $storedFormat = $post->fresh()->format_type;
        $this->post(route('posts.update', $post), ['name' => 'Edited default format article', 'status' => 'published', 'content' => '<p>Edited article body</p>'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($storedFormat, $post->fresh()->format_type);
        $edit = $this->get(route('posts.edit', $post), $this->headers())->assertOk();
        $this->assertNotContains('format_type', array_column($edit->json('props.fields'), 'name'));
        $this->assertSame($storedFormat, $edit->json('props.values.format_type'));
        $this->get($post->fresh()->url)->assertOk()->assertSee('Edited article body')->assertSee('Edited default format article');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_translated_block_preserves_default_raw_or_rich_content_and_renders_vietnamese_public_page(bool $raw): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        $this->assertTrue(LanguageAdvancedManager::isSupported(Block::class));
        LanguageAdvancedManager::initModelRelations();
        $originalRaw = $raw ? '<section><h2>English raw block</h2></section>' : '';
        $translatedRaw = $raw ? '<section><h2>Khối HTML tiếng Việt</h2></section>' : '';
        $this->post(route('block.store'), ['name' => 'English block', 'alias' => 'translated-acceptance-block', 'description' => 'English description', 'content' => '<p><strong>English rich block</strong></p>', 'raw_content' => $originalRaw, 'status' => 'published'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $block = Block::query()->where('alias', 'translated-acceptance-block')->sole();
        $original = $block->getRawOriginal();
        $this->post(route('block.update', $block).'?ref_lang=vi', ['name' => 'Khối tiếng Việt', 'alias' => 'translated-acceptance-block', 'description' => 'Mô tả tiếng Việt', 'content' => '<p><strong>Nội dung rich tiếng Việt</strong></p>', 'raw_content' => $translatedRaw, 'status' => 'published', 'language' => 'vi'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('blocks', ['id' => $block->id, 'content' => $original['content'], 'raw_content' => $original['raw_content'], 'name' => 'English block']);
        $this->get(route('block.edit', $block).'?ref_lang=vi', $this->headers())->assertOk()->assertJsonPath('props.values.content', '<p><strong>Nội dung rich tiếng Việt</strong></p>')->assertJsonPath('props.values.raw_content', $translatedRaw);
        $this->get(route('block.edit', $block).'?ref_lang=en_US', $this->headers())->assertOk()->assertJsonPath('props.values.content', $original['content'])->assertJsonPath('props.values.raw_content', $originalRaw);
        $this->publicTheme();
        $this->post(route('pages.store'), ['name' => 'Translated block host', 'content' => shortcode()->generateShortcode('static-block', ['alias' => $block->alias]), 'status' => 'published', 'slug' => 'translated-block-host', 'is_slug_editable' => 1], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $page = Page::query()->where('name', 'Translated block host')->sole();
        $this->get($page->url)->assertOk()->assertSee($raw ? 'English raw block' : 'English rich block');
        $this->app->instance('request', Request::create('/vi/translated-block-host'));
        Language::swap(new LanguageManager);
        Language::setLocale('vi');
        $this->app->register(LanguageServiceProvider::class, true);
        LanguageAdvancedManager::initModelRelations();
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->get('/vi/translated-block-host')->assertOk()->assertSee($raw ? 'Khối HTML tiếng Việt' : 'Nội dung rich tiếng Việt')->assertDontSee($raw ? 'English raw block' : 'English rich block');
    }

    private function menuFixture(): array
    {
        Menu::addMenuLocation('content-acceptance', 'Content acceptance');
        $tree = [['menuItem' => ['title' => 'Outer', 'url' => '/outer'], 'children' => [['menuItem' => ['title' => 'Removed parent', 'url' => '/parent'], 'children' => [['menuItem' => ['title' => 'First child', 'url' => '/first'], 'children' => [['menuItem' => ['title' => 'Grandchild', 'url' => '/grandchild']]]], ['menuItem' => ['title' => 'Second child', 'url' => '/second']]]], ['menuItem' => ['title' => 'Retained sibling', 'url' => '/sibling']]]], ['menuItem' => ['title' => 'Other root', 'url' => '/other']]];
        $this->post(route('menus.store'), ['name' => 'Parent promotion fixture', 'status' => 'published', 'locations' => ['content-acceptance'], 'menu_nodes' => json_encode($tree)], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $menu = MenuModel::query()->where('name', 'Parent promotion fixture')->sole();
        $nodes = $menu->menuNodes()->get()->keyBy('title');
        $leaf = fn (string $title, array $children = [], int $position = 0): array => ['menuItem' => ['id' => (string) $nodes[$title]->id, 'title' => $title, 'url' => $nodes[$title]->url, 'reference_type' => '', 'reference_id' => 0, 'icon_font' => '', 'icon_image' => '', 'css_class' => '', 'target' => '_self', 'position' => $position], 'children' => $children];
        $promoted = [$leaf('Outer', [$leaf('First child', [$leaf('Grandchild')]), $leaf('Second child', [], 1), $leaf('Retained sibling', [], 2)]), $leaf('Other root', [], 1)];

        return [$menu, $nodes, ['name' => 'Parent promotion fixture', 'status' => 'published', 'locations' => ['content-acceptance'], 'deleted_nodes' => (string) $nodes['Removed parent']->id, 'menu_nodes' => json_encode($promoted)]];
    }

    public function test_react_menu_remove_parent_payload_promotes_children_and_keeps_other_branches(): void
    {
        [$menu, $nodes, $payload] = $this->menuFixture();
        $this->post(route('menus.update', $menu), $payload, $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('menu_nodes', ['id' => $nodes['Removed parent']->id]);
        foreach (['First child', 'Second child', 'Retained sibling'] as $position => $title) {
            $this->assertSame($nodes['Outer']->id, $nodes[$title]->fresh()->parent_id);
            $this->assertSame($position, $nodes[$title]->fresh()->position);
        }
        $this->assertSame($nodes['First child']->id, $nodes['Grandchild']->fresh()->parent_id);
        $this->assertSame(6, $menu->menuNodes()->count());
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonCount(6, 'props.menu.nodes');
        Menu::load(true);
        $public = Menu::renderMenuLocation('content-acceptance');
        $this->assertStringNotContainsString('Removed parent', $public);
        foreach (['First child', 'Second child', 'Retained sibling', 'Grandchild', 'Other root'] as $title) {
            $this->assertStringContainsString($title, $public);
        }
    }

    public function test_menu_failure_after_parent_delete_rolls_back_all_nodes_and_locations(): void
    {
        [$menu, $nodes, $payload] = $this->menuFixture();
        $before = $menu->menuNodes()->orderBy('id')->get()->toArray();
        $updates = 0;
        MenuNode::updating(function (MenuNode $node) use ($nodes, &$updates): void {
            $updates++;
            if ($node->is($nodes['Second child'])) {
                throw new RuntimeException('Reject second promoted child');
            }
        });
        $this->post(route('menus.update', $menu), [...$payload, 'name' => 'Must roll back', 'locations' => []], $this->headers())->assertStatus(500);
        $this->assertGreaterThanOrEqual(2, $updates);
        $this->assertSame($before, $menu->fresh()->menuNodes()->orderBy('id')->get()->toArray());
        $this->assertSame('Parent promotion fixture', $menu->fresh()->name);
        $this->assertSame(['content-acceptance'], $menu->locations()->pluck('location')->all());
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonCount(7, 'props.menu.nodes');
    }

    #[TestWith([null])]
    #[TestWith(['apply'])]
    public function test_menu_update_feedback_preserves_legacy_message_in_each_inertia_redirect_response(?string $submitter): void
    {
        $menu = MenuModel::query()->create(['name' => 'Feedback fixture', 'status' => 'published']);
        $edit = route('menus.edit', $menu);
        $payload = ['name' => 'Legacy feedback name', 'status' => 'published', 'locations' => [], 'menu_nodes' => '[]'];
        config(['core.base.admin.legacy_mode' => true]);
        $expected = trans('core/base::notices.update_success_message');
        $this->postJson(route('menus.update', $menu), $payload)->assertOk()->assertJsonPath('error', false)->assertJsonPath('message', $expected);
        config(['core.base.admin.legacy_mode' => false]);
        $headers = [...$this->headers(), 'Accept' => 'text/html,application/xhtml+xml'];
        if ($submitter !== null) {
            $payload['submitter'] = $submitter;
        }
        foreach (['First React feedback name', 'Second React feedback name'] as $name) {
            $this->from($edit)->post(route('menus.update', $menu), [...$payload, 'name' => $name], $headers)
                ->assertRedirect($edit)->assertSessionHas('success_msg', $expected);
            $this->get($edit, $headers)->assertOk()->assertJsonPath('props.menu.name', $name)->assertJsonPath('props.flash.success', $expected);
        }
    }

    #[TestWith(['en_US'])]
    #[TestWith(['vi'])]
    #[TestWith(['en_US', 'menus.references'])]
    #[TestWith(['en_US', 'menus.references', true])]
    public function test_menu_language_schema_update_keeps_success_flash_through_all_redirects_with_file_sessions(string $code, ?string $interleaved = null, bool $invalid = false): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        config(['session.driver' => 'file', 'session.files' => storage_path('framework/sessions')]);
        File::ensureDirectoryExists(storage_path('framework/sessions'));
        $this->app['session']->forgetDrivers();
        $store = $this->app['session']->driver();
        $this->app->instance('session.store', $store);
        $this->app['redirect']->setSession($store);
        $this->assertInstanceOf(FileSessionHandler::class, $this->app['session']->driver()->getHandler());
        LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        Language::swap(new LanguageManager);
        Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        Language::initModelRelations();
        $this->app->register(HookServiceProvider::class, true);
        $headers = [...$this->headers(), 'Accept' => 'text/html,application/xhtml+xml'];
        $english = MenuModel::query()->create(['name' => 'English feedback menu', 'status' => 'published']);
        $menu = $english;
        if ($code === 'vi') {
            $this->post(route('menus.store', ['ref_lang' => 'vi']), ['name' => 'Vietnamese feedback menu', 'status' => 'published', 'locations' => [], 'menu_nodes' => '[]', 'language' => 'vi', 'ref_from' => $english->id], $headers)->assertRedirect()->assertSessionHasNoErrors();
            $menu = MenuModel::query()->where('name', 'Vietnamese feedback menu')->sole();
        }
        $edit = route('menus.edit', ['menu' => $menu->id, 'ref_lang' => $code]);
        foreach (['First localized feedback', 'Second localized feedback'] as $name) {
            $schema = $this->get($edit, $headers)->assertOk()->assertJsonPath('props.language.code', $code)->json('props');
            $this->withCookie(config('session.cookie'), $this->app['session']->driver()->getId());
            $payload = ['name' => $invalid ? '' : $name, 'status' => $schema['menu']['status'], 'locations' => $schema['menu']['locations'], 'menu_nodes' => '[]', 'deleted_nodes' => '', 'language' => $schema['language']['code'], 'ref_from' => $schema['language']['refFrom'] ?? '', 'submitter' => 'apply'];
            $expected = trans('core/base::notices.update_success_message');
            $response = $this->from($edit)->post($schema['urls']['submit'], $payload, $headers)->assertRedirect();
            if ($invalid) {
                $response->assertSessionHasErrors('name');
            } else {
                $response->assertSessionHas('success_msg', $expected);
            }
            $this->withCookie(config('session.cookie'), $this->app['session']->driver()->getId());
            if ($interleaved !== null) {
                $this->withCredentials()->getJson(route($interleaved))->assertOk();
            }
            $hops = 0;
            $redirects = [];
            while ($response->isRedirect() && $hops < 5) {
                $redirects[] = $response->headers->get('Location');
                $response = $this->get($response->headers->get('Location'), $headers);
                $hops++;
            }
            $this->assertLessThan(5, $hops);
            $response->assertOk()->assertJsonPath('props.menu.name', $invalid ? $schema['menu']['name'] : $name)->assertJsonPath('props.language.code', $code);
            if ($invalid) {
                $this->assertNotEmpty($response->json('props.errors.name'));
            } else {
                $response->assertJsonPath('props.flash.success', $expected);
            }
        }
    }

    #[TestWith(['json', true, true])]
    #[TestWith(['html', true, false])]
    #[TestWith(['inertia', true, false])]
    #[TestWith(['mutation', true, false])]
    #[TestWith(['json', false, false])]
    public function test_background_feedback_preserves_only_display_feedback_for_authenticated_json_reads(string $kind, bool $authenticated, bool $preserved): void
    {
        Route::middleware('web')->post('/admin/acceptance-feedback-seed', fn () => redirect('/admin/acceptance-feedback-probe')
            ->with('success_msg', 'Pending success')->with('error_msg', 'Pending failure')->with('acceptance_unrelated', 'Must expire')->with('_old_input', ['name' => 'Transient draft'])
            ->withErrors(['name' => 'Pending validation']));
        Route::middleware(['web', HandleAdminInertiaRequests::class.':menus'])->match(['GET', 'POST'], '/admin/acceptance-feedback-probe', function () use ($kind) {
            return match ($kind) {
                'html' => response()->view('core/base::admin.app', ['page' => ['component' => 'Menu/Form', 'props' => [], 'url' => '/admin/acceptance-feedback-probe', 'version' => null]]),
                'inertia' => Inertia::render('Menu/Form', []),
                default => response()->json(['ok' => true]),
            };
        });
        $this->post('/admin/acceptance-feedback-seed')->assertRedirect();
        if (! $authenticated) {
            Auth::logout();
        }
        $response = match ($kind) {
            'json' => $this->getJson('/admin/acceptance-feedback-probe'),
            'mutation' => $this->postJson('/admin/acceptance-feedback-probe'),
            'inertia' => $this->get('/admin/acceptance-feedback-probe', $this->headers()),
            default => $this->get('/admin/acceptance-feedback-probe'),
        };
        $response->assertOk();
        if ($kind === 'inertia') {
            $response->assertJsonPath('props.flash.success', 'Pending success')->assertJsonPath('props.flash.error', 'Pending failure')->assertJsonPath('props.errors.name', 'Pending validation');
        }
        $this->assertFalse(session()->has('acceptance_unrelated'));
        $this->assertFalse(session()->has('_old_input'));
        foreach (['success_msg', 'error_msg', 'errors'] as $key) {
            $this->assertSame($preserved, session()->has($key), $kind.': '.$key);
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_deleting_a_translated_category_parent_removes_descendant_translations_in_react_and_blade(bool $legacy): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $this->publicTheme();
        $this->assertTrue(LanguageAdvancedManager::isSupported(Category::class));
        LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        LanguageModel::query()->create(['lang_name' => 'French', 'lang_code' => 'fr', 'lang_locale' => 'fr', 'lang_flag' => 'fr', 'lang_order' => 3, 'lang_is_default' => false]);
        $parent = Category::query()->create(['name' => 'Parent', 'parent_id' => 0, 'author_id' => auth()->id(), 'author_type' => auth()->user()->getMorphClass()]);
        $child = Category::query()->create(['name' => 'Child', 'parent_id' => $parent->id, 'author_id' => auth()->id(), 'author_type' => auth()->user()->getMorphClass()]);
        $unrelated = Category::query()->create(['name' => 'Keep unrelated', 'parent_id' => 0, 'author_id' => auth()->id(), 'author_type' => auth()->user()->getMorphClass()]);
        $post = Post::query()->create(['name' => 'Preserved post', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => auth()->user()->getMorphClass()]);
        $post->categories()->attach([$parent->id, $child->id]);
        $this->withSession(['_token' => 'category-cascade-csrf'])->withHeader('X-CSRF-TOKEN', 'category-cascade-csrf');
        foreach ([$parent, $child, $unrelated] as $category) {
            foreach (['vi', 'fr'] as $locale) {
                Language::swap(new LanguageManager);
                $this->post(route('categories.update', ['category' => $category, 'ref_lang' => $locale]), ['name' => $locale.' translated '.$category->name, 'status' => 'published'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
                $this->assertDatabaseHas('categories_translations', ['categories_id' => $category->id, 'lang_code' => $locale]);
            }
        }
        Language::swap(new LanguageManager);
        $this->post(route('posts.update', ['post' => $post, 'ref_lang' => 'vi']), ['name' => 'Bài viết được giữ', 'content' => '<p>Nội dung</p>', 'status' => 'published'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        Language::swap(new LanguageManager);
        config(['core.base.admin.legacy_mode' => $legacy]);
        if ($legacy) {
            $this->deleteJson(route('categories.destroy', $parent))->assertOk()->assertJsonPath('error', false);
        } else {
            $this->delete(route('categories.destroy', $parent), [], $this->headers())->assertRedirect();
        }
        $this->assertModelMissing($parent);
        $this->assertModelMissing($child);
        $this->assertModelExists($post);
        $this->assertDatabaseCount('post_categories', 0);
        $this->assertDatabaseMissing('categories_translations', ['categories_id' => $parent->id]);
        $this->assertDatabaseMissing('categories_translations', ['categories_id' => $child->id]);
        $this->assertDatabaseHas('categories_translations', ['categories_id' => $unrelated->id, 'lang_code' => 'vi']);
        $this->assertDatabaseHas('categories_translations', ['categories_id' => $unrelated->id, 'lang_code' => 'fr']);
        $this->assertDatabaseHas('posts_translations', ['posts_id' => $post->id, 'lang_code' => 'vi', 'name' => 'Bài viết được giữ']);
    }
}
