<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Language\Models\LanguageMeta;
use Kern\Language\Providers\HookServiceProvider;
use Kern\Menu\Models\Menu;
use Kern\Menu\Models\MenuNode;
use Kern\PluginManagement\Services\PluginService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminMenuLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.menus' => 'inertia', 'core.base.admin.modules.widgets' => 'inertia', 'core.base.admin.modules.theme-options' => 'inertia']);
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
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    protected function payload(string $name, string $title): array
    {
        return ['name' => $name, 'status' => 'published', 'locations' => ['main-menu'], 'menu_nodes' => json_encode([['menuItem' => ['title' => $title, 'url' => '/'.$title]]]), 'submitter' => 'save'];
    }

    protected function createEnglishMenu(): Menu
    {
        $this->post(route('menus.store'), [...$this->payload('English navigation', 'English'), 'language' => 'en_US'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        return Menu::query()->where('name', 'English navigation')->firstOrFail();
    }

    public function test_menu_translations_have_separate_records_nodes_and_a_shared_menu_origin(): void
    {
        $source = $this->createEnglishMenu();
        $originalNode = $source->menuNodes()->firstOrFail();
        $this->get(route('menus.create', ['ref_lang' => 'vi', 'ref_from' => $source->getKey()]), $this->headers())->assertOk()->assertJsonPath('props.language.code', 'vi')->assertJsonPath('props.language.refFrom', (string) $source->getKey())->assertJsonPath('props.menu.id', null);
        $this->postJson(route('menus.store', ['ref_lang' => 'vi']), [...$this->payload('Vietnamese navigation', 'Vietnamese'), 'language' => 'vi', 'ref_from' => $source->getKey()], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $translated = Menu::query()->where('name', 'Vietnamese navigation')->firstOrFail();
        $this->assertNotSame($source->getKey(), $translated->getKey());
        $translatedNode = $translated->menuNodes()->firstOrFail();
        $this->assertNotSame($originalNode->getKey(), $translatedNode->getKey());
        $this->assertSame('English', $originalNode->fresh()->title);
        $this->assertSame(['main-menu'], $source->locations()->pluck('location')->all());
        $originalMeta = LanguageMeta::query()->where('reference_type', get_class($source))->where('reference_id', $source->getKey())->firstOrFail();
        $translatedMeta = LanguageMeta::query()->where('reference_type', get_class($translated))->where('reference_id', $translated->getKey())->firstOrFail();
        $this->assertSame($originalMeta->lang_meta_origin, $translatedMeta->lang_meta_origin);
        $this->assertSame('en_US', $originalMeta->lang_meta_code);
        $this->assertSame('vi', $translatedMeta->lang_meta_code);
        $this->assertDatabaseHas('language_meta', ['reference_type' => MenuNode::class, 'reference_id' => $translatedNode->getKey(), 'lang_meta_code' => 'vi']);
        $nodeOrigins = LanguageMeta::query()->where('reference_type', MenuNode::class)->whereIn('reference_id', [$originalNode->getKey(), $translatedNode->getKey()])->pluck('lang_meta_origin')->all();
        $this->assertCount(2, array_unique($nodeOrigins));
        $this->get(route('menus.edit', $translated), $this->headers())->assertOk()->assertJsonPath('props.language.code', 'vi')->assertJsonPath('props.menu.id', $translated->getKey());
        $this->get(route('menus.index', ['ref_lang' => 'vi']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1)->assertJsonPath('props.items.data.0.name', 'Vietnamese navigation');
        $this->get(route('menus.index'), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1)->assertJsonPath('props.items.data.0.name', 'English navigation');
        $this->get(route('menus.index', ['ref_lang' => 'all']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 2);
        Route::get('/language-menu-fixture/{code}', function (string $code): string {
            \Kern\Language\Facades\Language::setCurrentLocale($code === 'en_US' ? 'en' : $code);
            \Kern\Language\Facades\Language::setCurrentLocaleCode($code);
            \Kern\Language\Facades\Language::setCurrentAdminLocale($code);
            \Kern\Menu\Facades\Menu::load(true);

            return \Kern\Menu\Facades\Menu::renderMenuLocation('main-menu') ?: '';
        })->middleware('web');
        $this->get('/language-menu-fixture/vi')->assertOk()->assertSee('Vietnamese')->assertDontSee('English');
        $this->get('/language-menu-fixture/en_US')->assertOk()->assertSee('English')->assertDontSee('Vietnamese');

    }

    public function test_locale_movement_duplicate_translation_and_unrelated_source_are_rejected(): void
    {
        $source = $this->createEnglishMenu();
        $this->post(route('menus.update', $source), [...$this->payload('Cannot move original', 'Moved'), 'language' => 'vi', 'ref_lang' => 'vi'], $this->headers())->assertSessionHasErrors('language');
        $this->assertSame('English navigation', $source->fresh()->name);
        $payload = [...$this->payload('Vietnamese navigation', 'Vietnamese'), 'language' => 'vi', 'ref_from' => $source->getKey()];
        $this->post(route('menus.store', ['ref_lang' => 'vi']), $payload, $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('menus.store', ['ref_lang' => 'vi']), [...$payload, 'name' => 'Duplicate locale'], $this->headers())->assertSessionHasErrors('language');
        $this->assertDatabaseCount('menus', 2);
        $this->post(route('menus.store'), [...$this->payload('Unrelated navigation', 'Unrelated'), 'language' => 'en_US'], $this->headers())->assertRedirect();
        $other = Menu::query()->where('name', 'Unrelated navigation')->firstOrFail();
        $translated = Menu::query()->where('name', 'Vietnamese navigation')->firstOrFail();
        $this->post(route('menus.update', $translated), [...$payload, 'ref_from' => $other->getKey()], $this->headers())->assertSessionHasErrors('ref_from');
        $this->assertDatabaseCount('menus', 3);
        $this->get(route('menus.create', ['ref_lang' => 'unknown']), $this->headers())->assertRedirect()->assertSessionHasErrors('language');
    }

    public function test_locale_inferred_from_default_or_query_tags_menu_and_nodes_without_body_language(): void
    {
        $this->post(route('menus.store'), $this->payload('Inferred English navigation', 'English'), $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $source = Menu::query()->where('name', 'Inferred English navigation')->firstOrFail();
        $this->assertDatabaseHas('language_meta', ['reference_type' => Menu::class, 'reference_id' => $source->getKey(), 'lang_meta_code' => 'en_US']);
        $this->assertDatabaseHas('language_meta', ['reference_type' => MenuNode::class, 'reference_id' => $source->menuNodes()->firstOrFail()->getKey(), 'lang_meta_code' => 'en_US']);
        $this->post(route('menus.store', ['ref_lang' => 'vi']), [...$this->payload('Inferred Vietnamese navigation', 'Vietnamese'), 'ref_from' => $source->getKey()], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $translated = Menu::query()->where('name', 'Inferred Vietnamese navigation')->firstOrFail();
        $this->assertDatabaseHas('language_meta', ['reference_type' => Menu::class, 'reference_id' => $translated->getKey(), 'lang_meta_code' => 'vi']);
        $this->assertDatabaseHas('language_meta', ['reference_type' => MenuNode::class, 'reference_id' => $translated->menuNodes()->firstOrFail()->getKey(), 'lang_meta_code' => 'vi']);
    }

    public function test_language_helper_preserves_default_legacy_records_and_ignores_renderer_flag(): void
    {
        $legacy = Menu::query()->create(['name' => 'Legacy untagged menu', 'status' => 'published']);
        $this->get(route('menus.index'), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1);
        $this->get(route('menus.edit', $legacy), $this->headers())->assertOk()->assertJsonPath('props.language.code', 'en_US');
        $this->actingAs(UserFactory::new()->create(['permissions' => ['menus.index' => true]]));
        $this->post(route('menus.store'), [...$this->payload('Denied navigation', 'Denied'), 'language' => 'vi'], $this->headers())->assertForbidden();
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.admin.modules.menus' => 'blade']);
        $this->get(route('menus.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Menu/Index');
        $this->assertDatabaseCount('menus', 1);
    }

    public function test_menu_registered_filters_and_exports_preserve_the_selected_language(): void
    {
        $source = $this->createEnglishMenu();
        $this->post(route('menus.store', ['ref_lang' => 'vi']), [...$this->payload('Vietnamese navigation', 'Vietnamese'), 'language' => 'vi', 'ref_from' => $source->getKey()], $this->headers())->assertSessionHasNoErrors();
        $filters = [['field' => 'name', 'operator' => 'like', 'value' => 'Vietnamese'], ['field' => 'created_at', 'operator' => '>=', 'value' => now()->subDay()->format('Y-m-d')]];
        $this->get(route('menus.index', ['ref_lang' => 'vi', 'advanced' => $filters]), $this->headers())
            ->assertOk()->assertJsonPath('props.items.total', 1)->assertJsonPath('props.filters.advanced', $filters)->assertJsonPath('props.bulkFields.0.name', 'name');
        $this->get(route('menus.index', ['ref_lang' => 'vi', 'advanced' => [['field' => 'name', 'operator' => 'raw', 'value' => 'Vietnamese']]]), $this->headers())
            ->assertSessionHasErrors('advanced.0');
        setting()->set('datatables_default_show_export_button', '1')->save();
        $response = $this->getJson(route('menus.index', ['ref_lang' => 'vi', 'advanced' => $filters, '_export' => 'csv']))->assertOk()->assertHeader('content-disposition');
        $export = json_encode(IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray());
        $this->assertStringContainsString('Vietnamese navigation', $export);
        $this->assertStringNotContainsString('English navigation', $export);
    }

    public function test_menu_bulk_registered_changes_keep_locale_redirects_and_reject_cross_locale_or_invalid_selections(): void
    {
        $source = $this->createEnglishMenu();
        $this->post(route('menus.store', ['ref_lang' => 'vi']), [...$this->payload('Vietnamese navigation', 'Vietnamese'), 'language' => 'vi', 'ref_from' => $source->getKey()], $this->headers())->assertSessionHasNoErrors();
        $translated = Menu::query()->where('name', 'Vietnamese navigation')->sole();
        $url = route('menus.bulk', ['ref_lang' => 'vi']);
        $payload = ['ids' => [$translated->getKey()], 'action' => 'change', 'field' => 'name', 'value' => 'Renamed Vietnamese'];
        $this->post($url, $payload, $this->headers())->assertRedirect(route('menus.index', ['ref_lang' => 'vi']))->assertSessionHasNoErrors();
        $this->assertSame('Renamed Vietnamese', $translated->fresh()->name);
        $this->post($url, [...$payload, 'field' => 'created_at', 'value' => '2026-01-02'], $this->headers())->assertSessionHasNoErrors();
        $this->assertSame('2026-01-02', $translated->fresh()->created_at->format('Y-m-d'));
        $this->post($url, ['ids' => [$translated->getKey()], 'action' => 'status', 'status' => 'draft'], $this->headers())->assertSessionHasNoErrors();
        $this->assertSame('draft', $translated->fresh()->status->getValue());
        foreach ([['ids' => [$source->getKey()]], ['ids' => [$translated->getKey(), 999999]], ['ids' => [$translated->getKey(), $translated->getKey()]], ['field' => 'slug'], ['field' => 'created_at', 'value' => 'not-a-date']] as $invalid) {
            $this->post($url, [...$payload, ...$invalid], $this->headers())->assertSessionHasErrors();
        }
        $this->assertSame('English navigation', $source->fresh()->name);
        $this->assertSame('Renamed Vietnamese', $translated->fresh()->name);
        $this->assertSame('2026-01-02', $translated->fresh()->created_at->format('Y-m-d'));
    }
}
