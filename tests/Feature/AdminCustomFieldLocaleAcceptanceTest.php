<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kern\Blog\Models\Post;
use Kern\CustomField\Models\CustomField;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Language\Facades\Language;
use Kern\Language\Models\Language as LanguageModel;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminCustomFieldLocaleAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);

        foreach (['custom-field'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }

        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
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

    #[TestWith(['pages', Page::class])]
    #[TestWith(['posts', Post::class])]
    public function test_custom_fields_use_locale_values_without_manual_registration(string $routePrefix, string $modelClass): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $pluginService = app(PluginService::class);
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse($pluginService->activate($plugin)['error'], $plugin);
            }
        }
        if (! is_plugin_active('blog')) {
            $this->assertFalse($pluginService->activate('blog')['error']);
        }
        $this->assertTrue(LanguageAdvancedManager::isSupported(CustomField::class));

        LanguageModel::query()->create(['lang_name' => 'English', 'lang_locale' => 'en', 'lang_code' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us', 'lang_order' => 1]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_locale' => 'vi', 'lang_code' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn', 'lang_order' => 2]);
        Language::setSupportedLocales([
            'en' => ['lang_name' => 'English', 'lang_code' => 'en', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'],
            'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn'],
        ]);

        $group = FieldGroup::query()->create([
            'title' => 'Acceptance translated fields',
            'status' => 'published',
            'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => $modelClass]]]),
        ]);
        $translatedField = FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Subtitle', 'slug' => 'subtitle', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        $fallbackField = FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Fallback text', 'slug' => 'fallback_text', 'type' => 'text', 'options' => '{}', 'order' => 2]);

        $this->post(route($routePrefix.'.store'), [
            'name' => 'Acceptance locale custom fields',
            'content' => '<p>English content</p>',
            'status' => 'published',
            'template' => 'default',
            'slug' => 'acceptance-locale-custom-fields',
            'is_slug_editable' => 1,
        ], $this->headers())->assertRedirect();
        $page = $modelClass::query()->where('name', 'Acceptance locale custom fields')->firstOrFail();
        $groups = $this->get(route($routePrefix.'.edit', $page), $this->headers())->assertOk()->json('props.customFieldGroups');
        $groups[0]['items'][0]['value'] = 'Default subtitle';
        $groups[0]['items'][1]['value'] = 'Default fallback';
        $this->post(route($routePrefix.'.update', $page), [
            'name' => $page->name,
            'content' => $page->content,
            'status' => 'published',
            'custom_fields' => json_encode($groups),
        ], $this->headers())->assertRedirect();

        $translatedGroups = $this->get(route($routePrefix.'.edit', $page).'?ref_lang=vi', $this->headers())
            ->assertOk()->assertJsonPath('props.language', 'vi')->json('props.customFieldGroups');
        $translatedGroups[0]['items'][0]['value'] = 'Tiêu đề phụ';
        $this->post(route($routePrefix.'.update', $page).'?ref_lang=vi', [
            'name' => 'Acceptance locale custom fields Vietnamese',
            'content' => '<p>Nội dung tiếng Việt</p>',
            'language' => 'vi',
            'status' => 'published',
            'template' => 'default',
            'slug' => 'acceptance-locale-custom-fields-vi',
            'custom_fields' => json_encode($translatedGroups),
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $defaultValue = CustomField::query()->where('use_for', $modelClass)->where('use_for_id', $page->getKey())->where('field_item_id', $translatedField->getKey())->firstOrFail();
        $fallbackValue = CustomField::query()->where('use_for', $modelClass)->where('use_for_id', $page->getKey())->where('field_item_id', $fallbackField->getKey())->firstOrFail();
        $this->assertSame('Default subtitle', $defaultValue->value);
        $this->assertSame('Default fallback', $fallbackValue->value);
        $this->assertDatabaseHas('custom_fields_translations', ['custom_fields_id' => $defaultValue->getKey(), 'lang_code' => 'vi', 'value' => 'Tiêu đề phụ']);
        $localeForm = $this->get(route($routePrefix.'.edit', $page).'?ref_lang=vi', $this->headers())->assertOk();
        $localeValues = collect($localeForm->json('props.customFieldGroups.0.items'))->keyBy('slug');
        $this->assertSame('Tiêu đề phụ', $localeValues['subtitle']['value'], 'A translated field should reopen with its locale-specific value.');
        $this->assertSame('Default fallback', $localeValues['fallback_text']['value']);
        $this->get(route($routePrefix.'.edit', $page), $this->headers())->assertOk()
            ->assertJsonPath('props.customFieldGroups.0.items.0.value', 'Default subtitle');

        foreach (['0', ''] as $value) {
            DB::table('custom_fields_translations')
                ->where('custom_fields_id', $defaultValue->getKey())->where('lang_code', 'vi')->update(['value' => $value]);
            $this->get(route($routePrefix.'.edit', $page).'?ref_lang=vi', $this->headers())->assertOk()
                ->assertJsonPath('props.customFieldGroups.0.items.0.value', $value);
        }
    }
}
