<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Language\Providers\HookServiceProvider;
use Kern\Menu\Models\Menu;
use Kern\Menu\Models\MenuNode;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminMenuSearchParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.menus' => 'inertia']);
    }

    public function test_picker_searches_beyond_five_hundred_records_and_paginates_only_published_content(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        PageFactory::new()->count(501)->create(['name' => 'Earlier content']);
        $target = PageFactory::new()->create(['name' => 'Zebra destination']);
        PageFactory::new()->create(['name' => 'Zebra draft', 'status' => 'draft']);

        $this->getJson(route('menus.references', ['type' => Page::class, 'search' => 'Zebra']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', (string) $target->id)->assertJsonPath('data.0.title', 'Zebra destination');
        $this->getJson(route('menus.references', ['type' => Page::class, 'page' => 2]))->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('current_page', 2)->assertJsonPath('total', 502);
        $this->getJson(route('menus.references', ['type' => 'UnregisteredModel']))->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_picker_requires_a_menu_editor_and_does_not_remove_existing_draft_references(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['menus.index' => true]]));
        $this->getJson(route('menus.references'))->assertForbidden();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['menus.create' => true]]));
        $this->getJson(route('menus.references', ['type' => Page::class]))->assertOk();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $draft = PageFactory::new()->create(['status' => 'draft']);
        $menu = Menu::query()->create(['name' => 'Existing navigation', 'status' => 'published']);
        MenuNode::query()->create(['menu_id' => $menu->id, 'title' => 'Retained label', 'reference_type' => Page::class, 'reference_id' => $draft->id, 'url' => '/existing', 'parent_id' => 0, 'position' => 0]);
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];

        $this->get(route('menus.edit', $menu), $headers)->assertOk()->assertJsonPath('props.menu.nodes.0.reference_id', (string) $draft->id)->assertJsonPath('props.menu.nodes.0.title', 'Retained label');
    }

    public function test_picker_searches_translated_names_in_the_selected_menu_language(): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                app(PluginService::class)->activate($plugin);
            }
        }
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_default' => true, 'lang_order' => 1]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_is_default' => false, 'lang_order' => 2]);
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        \Kern\Language\Facades\Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        \Kern\Language\Facades\Language::initModelRelations();
        $this->app->register(HookServiceProvider::class, true);
        $page = PageFactory::new()->create(['name' => 'English original']);
        DB::table('pages_translations')->insert(['pages_id' => $page->id, 'lang_code' => 'vi', 'name' => 'Trang dich', 'description' => '', 'content' => '']);
        $this->actingAs(UserFactory::new()->superUser()->create());

        $this->getJson(route('menus.references', ['type' => Page::class, 'search' => 'Trang', 'ref_lang' => 'vi']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', (string) $page->id)->assertJsonPath('data.0.title', 'Trang dich');
        $this->getJson(route('menus.references', ['type' => Page::class, 'search' => 'Trang', 'ref_lang' => 'en_US']))->assertOk()->assertJsonPath('total', 0);
    }
}
