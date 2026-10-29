<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\PluginManagement\Services\PluginService;
use Kern\Setting\Facades\Setting;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Facades\ThemeOption;
use Kern\Theme\Services\ThemeService;
use Kern\Widget\Widgets\Text;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminAppearanceLanguagesTest extends TestCase
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
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    protected function registerOptions(): void
    {
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        Theme::uses('ripple');
        ThemeOption::setSection(['id' => 'language-test', 'title' => 'Language test', 'fields' => [['id' => 'language_title', 'type' => 'text', 'label' => 'Language title', 'attributes' => ['name' => 'language_title', 'value' => '']]]]);
    }

    public function test_widget_chooser_and_writes_keep_locales_isolated_and_normalize_default_code(): void
    {
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        Theme::uses('ripple');
        $english = [['sidebar_id' => 'primary_sidebar', 'items' => [['id' => Text::class, 'name' => 'English widget', 'content' => 'English content']]]];
        $vietnamese = [['sidebar_id' => 'primary_sidebar', 'items' => [['id' => Text::class, 'name' => 'Vietnamese widget', 'content' => 'Vietnamese content']]]];
        $this->post(route('widgets.save_all', ['ref_lang' => 'en_US']), ['groups' => $english], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('widgets.save_all', ['ref_lang' => 'vi']), ['groups' => $vietnamese], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('widgets', ['theme' => 'ripple', 'sidebar_id' => 'primary_sidebar']);
        $this->assertDatabaseHas('widgets', ['theme' => 'ripple-vi', 'sidebar_id' => 'primary_sidebar']);
        $this->assertDatabaseMissing('widgets', ['theme' => 'ripple-en_US']);
        $response = $this->get(route('widgets.index', ['ref_lang' => 'vi']), $this->headers())->assertOk()->assertJsonPath('props.language', 'vi');
        $this->assertStringContainsString('Vietnamese widget', json_encode($response->json('props.groups')));
        $this->assertStringNotContainsString('English widget', json_encode($response->json('props.groups')));
        $choices = collect($response->json('props.languages'))->keyBy('code');
        $this->assertStringNotContainsString('ref_lang', $choices['en_US']['url']);
        $this->assertStringContainsString('ref_lang=vi', $choices['vi']['url']);
        $this->get(route('widgets.index', ['ref_lang' => 'en_US']), $this->headers())->assertOk()->assertJsonPath('props.language', 'en_US');
    }

    public function test_theme_option_section_links_and_saves_keep_the_selected_locale(): void
    {
        $this->registerOptions();
        $this->post(route('theme.options.post', 'language-test'), ['language_title' => 'English title'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('theme.options.post', ['id' => 'language-test', 'ref_lang' => 'vi']), ['language_title' => 'Vietnamese title'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('English title', Setting::get('theme-ripple-language_title'));
        $this->assertSame('Vietnamese title', Setting::get('theme-ripple-vi-language_title'));
        $response = $this->get(route('theme.options', ['id' => 'language-test', 'ref_lang' => 'vi']), $this->headers())->assertOk()->assertJsonPath('props.language', 'vi');
        $section = collect($response->json('props.sections'))->firstWhere('id', 'language-test');
        $this->assertSame('Vietnamese title', collect($section['fields'])->firstWhere('name', 'language_title')['value']);
        $this->assertStringContainsString('ref_lang=vi', $section['url']);
        $this->assertStringContainsString('ref_lang=vi', $response->json('props.submit'));
        $choices = collect($response->json('props.languages'))->keyBy('code');
        $this->assertStringContainsString('language-test', $choices['en_US']['url']);
        $this->assertStringNotContainsString('ref_lang', $choices['en_US']['url']);
        $this->get(route('theme.options', ['id' => 'language-test', 'ref_lang' => 'en_US']), $this->headers())->assertOk()->assertJsonPath('props.language', 'en_US');
    }

    public function test_invalid_appearance_locale_is_rejected_before_writes(): void
    {
        $this->registerOptions();
        $this->post(route('widgets.save_all', ['ref_lang' => '../../bad']), ['groups' => []], $this->headers())->assertSessionHasErrors('ref_lang');
        $this->post(route('theme.options.post', ['id' => 'language-test', 'ref_lang' => '../../bad']), ['language_title' => 'Injected'], $this->headers())->assertSessionHasErrors('ref_lang');
        $this->assertDatabaseCount('widgets', 0);
        $this->assertNull(Setting::get('theme-ripple-../../bad-language_title'));
    }
}
