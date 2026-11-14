<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Kern\Contact\Enums\CustomFieldType;
use Kern\Contact\Forms\Fronts\ContactForm;
use Kern\Contact\Models\Contact;
use Kern\Contact\Models\CustomField;
use Kern\Contact\Models\CustomFieldOption;
use Kern\Language\Facades\Language;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language as LanguageModel;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminContactFieldsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        foreach (['language', 'language-advanced', 'contact'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    public function test_contact_field_list_restores_type_column_and_enum_labels(): void
    {
        $fields = collect(CustomFieldType::values())->map(fn ($type) => CustomField::query()->create(['name' => 'Field '.$type, 'type' => $type, 'required' => false, 'order' => 0]));
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
        $response = $this->get(route('contacts.custom-fields.index', ['sort' => 'type', 'direction' => 'asc']), $headers)->assertOk();
        $this->assertSame(['id', 'name', 'type', 'created_at'], array_column($response->json('props.columns'), 'key'));
        $column = collect($response->json('props.columns'))->firstWhere('key', 'type');
        $this->assertSame(trans('plugins/contact::contact.custom_field.type'), $column['label']);
        $this->assertTrue($column['sortable']);
        $rows = collect($response->json('props.items.data'))->keyBy('id');
        foreach ($fields as $field) {
            $this->assertSame($field->type->getValue(), $rows[$field->getKey()]['type']);
            $this->assertSame($field->type->label(), $rows[$field->getKey()]['type_label']);
        }
        $this->assertSame($rows->pluck('type')->sort()->values()->all(), $rows->pluck('type')->values()->all());
    }

    #[TestWith(['apply'])]
    #[TestWith(['save'])]
    public function test_contact_field_create_uses_the_requested_save_destination(string $submitter): void
    {
        $response = $this->from(route('contacts.custom-fields.create'))->post(route('contacts.custom-fields.store'), [
            'name' => 'Department', 'type' => 'dropdown', 'required' => '1', 'order' => 0, 'submitter' => $submitter,
            'options' => [['label' => 'Sales', 'value' => 'sales', 'order' => 0]],
        ]);
        $response->assertSessionHasNoErrors();
        $field = CustomField::query()->sole();
        $response->assertRedirect($submitter === 'apply' ? route('contacts.custom-fields.edit', $field) : route('contacts.custom-fields.index'));
        $this->assertSame('sales', $field->options()->sole()->value);
    }

    public function test_changing_dropdown_to_text_clears_obsolete_options_but_empty_dropdown_remains_invalid(): void
    {
        $field = CustomField::query()->create(['name' => 'Department', 'type' => 'dropdown', 'required' => true, 'order' => 0]);
        $option = $field->options()->create(['label' => 'Sales', 'value' => 'sales', 'order' => 0]);
        $payload = ['name' => 'Department', 'type' => 'dropdown', 'required' => '1', 'order' => 0, 'submitter' => 'apply', 'options' => []];
        $this->from(route('contacts.custom-fields.edit', $field))->post(route('contacts.custom-fields.update', $field), $payload)
            ->assertSessionHasErrors('options');
        $this->assertModelExists($option);
        $this->from(route('contacts.custom-fields.edit', $field))->post(route('contacts.custom-fields.update', $field), [...$payload, 'type' => 'text'])
            ->assertRedirect(route('contacts.custom-fields.edit', $field))->assertSessionHasNoErrors();
        $this->assertSame('text', $field->fresh()->type->getValue());
        $this->assertSame(0, $field->options()->count());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_existing_checkbox_defaults_submit_without_toggling(bool $required): void
    {
        $field = CustomField::query()->create(['name' => 'Checkbox defaults', 'type' => 'text', 'required' => $required, 'order' => 0]);
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
        $form = $this->get(route('contacts.custom-fields.edit', $field), $headers)->assertOk();
        $values = $form->json('props.values');
        $this->assertSame((int) $required, $values['required']);
        $this->from(route('contacts.custom-fields.edit', $field))->post(route('contacts.custom-fields.update', $field), [...$values, 'placeholder' => 'Saved without toggling', 'submitter' => 'apply'], $headers)
            ->assertRedirect(route('contacts.custom-fields.edit', $field))->assertSessionHasNoErrors();
        $this->assertSame($required, $field->fresh()->required);
        $this->assertSame('Saved without toggling', $field->fresh()->placeholder);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_contact_field_json_checkbox_booleans_use_the_same_on_off_contract(bool $required): void
    {
        $payload = ['name' => 'Boolean checkbox', 'type' => 'text', 'required' => $required, 'order' => 999, 'options' => []];
        $this->postJson(route('contacts.custom-fields.store'), $payload)->assertSuccessful()->assertJsonPath('error', false);
        $this->assertDatabaseHas('contact_custom_fields', ['name' => 'Boolean checkbox', 'required' => (int) $required]);
    }

    public function test_contact_field_boolean_normalization_does_not_accept_invalid_required_values(): void
    {
        $payload = ['name' => 'Invalid checkbox', 'type' => 'text', 'order' => 999, 'options' => []];
        foreach ([['required' => null], ['required' => ''], ['required' => 'false'], ['required' => '2'], ['required' => []], ['required' => ['invalid']]] as $invalid) {
            $this->postJson(route('contacts.custom-fields.store'), [...$payload, ...$invalid])->assertUnprocessable()->assertJsonValidationErrors('required');
        }
        $this->assertDatabaseCount('contact_custom_fields', 0);
    }

    public function test_translated_contact_field_submits_exact_inertia_values_and_option_payload(): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $field = CustomField::query()->create(['name' => 'Lĩnh vực nghiệm thu', 'placeholder' => '', 'type' => 'dropdown', 'required' => false, 'order' => 999]);
        $field->saveOptions([['label' => 'Phát triển', 'value' => 'development', 'order' => 0], ['label' => 'Thiết kế', 'value' => 'design', 'order' => 1]]);
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $this->withSession(['_token' => 'contact-fields-real-payload'])->withHeader('X-CSRF-TOKEN', 'contact-fields-real-payload');
        Language::swap(new LanguageManager);
        $edit = route('contacts.custom-fields.edit', $field).'?ref_lang=vi';
        $props = $this->get($edit, $headers)->assertOk()->assertJsonPath('props.language', 'vi')->json('props');
        $options = array_map(fn (array $option, int $order): array => [...$option, 'order' => $order], $props['fieldOptions'], array_keys($props['fieldOptions']));
        $options[0]['label'] = 'Phát triển tiếng Việt nghiệm thu';
        $options[0]['value'] = 'phat-trien-nghiem-thu';
        $payload = [...$props['values'], '_method' => 'POST', 'submitter' => 'apply', 'language' => $props['language'], 'options' => $options];
        Language::swap(new LanguageManager);
        $this->from($edit)->post($props['urls']['submit'], $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contact_custom_field_options_translations', ['contact_custom_field_options_id' => $options[0]['id'], 'lang_code' => 'vi', 'label' => $options[0]['label'], 'value' => $options[0]['value']]);
        $this->assertDatabaseHas('contact_custom_fields', ['id' => $field->id, 'required' => 0, 'order' => 999]);
    }

    #[TestWith(['dropdown'])]
    #[TestWith(['radio'])]
    public function test_translated_contact_fields_and_options_preserve_default_values_and_shared_properties(string $type): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->assertTrue(LanguageAdvancedManager::isSupported(CustomField::class));
        $this->assertTrue(LanguageAdvancedManager::isSupported(CustomFieldOption::class));
        foreach ([['English', 'en_US', 'en', 'us', true], ['Vietnamese', 'vi', 'vi', 'vn', false]] as [$name, $code, $locale, $flag, $default]) {
            LanguageModel::query()->create(['lang_name' => $name, 'lang_code' => $code, 'lang_locale' => $locale, 'lang_flag' => $flag, 'lang_order' => $default ? 1 : 2, 'lang_is_default' => $default]);
        }
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $this->withSession(['_token' => 'contact-fields-locale'])->withHeader('X-CSRF-TOKEN', 'contact-fields-locale');
        $payload = ['name' => 'Department', 'placeholder' => 'Choose department', 'type' => $type, 'required' => '1', 'order' => 4, 'submitter' => 'apply', 'options' => [['label' => 'Sales', 'value' => 'sales', 'order' => 0], ['label' => 'Support', 'value' => '0', 'order' => 1]]];
        $this->post(route('contacts.custom-fields.store'), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        $field = CustomField::query()->sole();
        $edit = route('contacts.custom-fields.edit', $field);
        $update = route('contacts.custom-fields.update', $field).'?ref_lang=vi';
        Language::swap(new LanguageManager);
        $default = $this->get($edit, $headers)->assertOk()->assertJsonPath('props.language', 'en_US');
        $englishOptions = $default->json('props.fieldOptions');
        $this->assertCount(2, $englishOptions);
        Language::swap(new LanguageManager);
        $this->get($edit.'?ref_lang=vi', $headers)->assertOk()->assertJsonPath('props.values.name', 'Department')->assertJsonPath('props.fieldOptions', $englishOptions);
        $translatedOptions = array_map(fn (array $option, int $index): array => [...$option, 'label' => ['Bán hàng', 'Hỗ trợ'][$index], 'value' => ['ban-hang', '0'][$index]], $englishOptions, array_keys($englishOptions));
        $translatedPayload = [...$payload, 'name' => 'Bộ phận', 'placeholder' => 'Chọn bộ phận', 'type' => $type === 'dropdown' ? 'radio' : 'dropdown', 'required' => '0', 'order' => 99, 'options' => array_map(fn (array $option): array => [...$option, 'order' => 99], $translatedOptions)];
        Language::swap(new LanguageManager);
        $this->from($edit.'?ref_lang=vi')->post($update, $translatedPayload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        Language::swap(new LanguageManager);
        $this->get($edit.'?ref_lang=vi', $headers)->assertOk()->assertJsonPath('props.values.name', 'Bộ phận')->assertJsonPath('props.values.placeholder', 'Chọn bộ phận')->assertJsonPath('props.fieldOptions', $translatedOptions);
        Language::swap(new LanguageManager);
        $this->get($edit, $headers)->assertOk()->assertJsonPath('props.values.name', 'Department')->assertJsonPath('props.values.placeholder', 'Choose department')->assertJsonPath('props.fieldOptions', $englishOptions);
        $this->assertDatabaseHas('contact_custom_fields', ['id' => $field->id, 'name' => 'Department', 'placeholder' => 'Choose department', 'type' => $type, 'required' => 1, 'order' => 4]);
        foreach ($englishOptions as $index => $option) {
            $this->assertDatabaseHas('contact_custom_field_options', [...$option, 'custom_field_id' => $field->id]);
            $this->assertDatabaseHas('contact_custom_field_options_translations', ['contact_custom_field_options_id' => $option['id'], 'lang_code' => 'vi', 'label' => $translatedOptions[$index]['label'], 'value' => $translatedOptions[$index]['value']]);
        }

        $foreign = CustomField::query()->create(['name' => 'Foreign field', 'type' => 'dropdown', 'required' => false, 'order' => 0]);
        $foreignOption = $foreign->options()->create(['label' => 'Foreign', 'value' => 'foreign', 'order' => 0]);
        $tables = ['contact_custom_fields', 'contact_custom_fields_translations', 'contact_custom_field_options', 'contact_custom_field_options_translations'];
        $snapshot = fn (): array => collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->toArray()])->all();
        $before = $snapshot();
        foreach ([[...$translatedOptions[0], 'id' => (string) $foreignOption->id], [...$translatedOptions[0], 'label' => '']] as $invalidOption) {
            Language::swap(new LanguageManager);
            $this->post($update, [...$translatedPayload, 'name' => 'Must not save', 'options' => [$invalidOption, $translatedOptions[1]]], $headers)->assertSessionHasErrors();
            $this->assertEquals($before, $snapshot());
        }
        Language::swap(new LanguageManager);
        $this->post(route('contacts.custom-fields.update', $field), [...$payload, 'name' => 'Default edited without language', 'options' => $englishOptions], $headers)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contact_custom_fields', ['id' => $field->id, 'name' => 'Default edited without language']);
        $this->assertDatabaseHas('contact_custom_fields_translations', ['contact_custom_fields_id' => $field->id, 'lang_code' => 'vi', 'name' => 'Bộ phận']);
        Mail::fake();
        auth()->logout();
        $this->app->instance('request', Request::create('/vi/contact/send', 'POST'));
        Language::swap(new LanguageManager);
        Language::setLocale();
        LanguageAdvancedManager::initModelRelations();
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $publicForm = ContactForm::create();
        $this->assertSame(($type === 'dropdown' ? ['' => 'Chọn bộ phận'] : []) + ['ban-hang' => 'Bán hàng', '0' => 'Hỗ trợ'], $publicForm->getField("contact_custom_fields[$field->id]")->getOptions()['choices']);
        $publicPayload = ['name' => 'Locale sender', 'email' => 'sender@example.test', 'content' => 'Translated department message', 'contact_custom_fields' => [$field->id => 'ban-hang']];
        $this->postJson(route('public.send.contact'), $publicPayload)->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertSame(['Bộ phận' => 'Bán hàng'], Contact::query()->sole()->custom_fields);
        $this->postJson(route('public.send.contact'), [...$publicPayload, 'contact_custom_fields' => [$field->id => '0']])->assertOk()->assertJsonPath('error', false);
        $this->assertSame(['Bộ phận' => 'Hỗ trợ'], Contact::query()->latest('id')->firstOrFail()->custom_fields);
        $this->postJson(route('public.send.contact'), [...$publicPayload, 'contact_custom_fields' => [$field->id => 'sales']])->assertUnprocessable()->assertJsonValidationErrors("contact_custom_fields.$field->id");
        $this->postJson(route('public.send.contact'), [...$publicPayload, 'contact_custom_fields' => [$field->id => 0]])->assertUnprocessable()->assertJsonValidationErrors("contact_custom_fields.$field->id");
        $this->assertDatabaseCount('contacts', 2);
        session()->flush();
        $this->withSession(['_token' => 'contact-fields-locale']);
        $this->app->instance('request', Request::create('/contact/send', 'POST'));
        Language::swap(new LanguageManager);
        Language::setLocale();
        LanguageAdvancedManager::initModelRelations();
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->postJson(route('public.send.contact'), [...$publicPayload, 'contact_custom_fields' => [$field->id => '0']])->assertOk()->assertJsonPath('error', false);
        $this->assertSame(['Default edited without language' => 'Support'], Contact::query()->latest('id')->firstOrFail()->custom_fields);
        $this->assertDatabaseCount('contacts', 3);
    }

    #[TestWith(['remove-option'])]
    #[TestWith(['change-type'])]
    #[TestWith(['delete-field'])]
    public function test_removed_contact_options_clean_their_translations_without_affecting_other_fields(string $action): void
    {
        LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $payload = ['name' => 'Department', 'placeholder' => 'Choose', 'type' => 'dropdown', 'required' => '0', 'order' => 1, 'options' => [['label' => 'Sales', 'value' => 'sales', 'order' => 0], ['label' => 'Support', 'value' => 'support', 'order' => 1]]];
        foreach (['Target field', 'Unrelated field'] as $name) {
            Language::swap(new LanguageManager);
            $this->post(route('contacts.custom-fields.store'), [...$payload, 'name' => $name], $headers)->assertRedirect()->assertSessionHasNoErrors();
            $field = CustomField::query()->where('name', $name)->sole();
            $options = $field->options()->orderBy('order')->get()->map(fn ($option): array => ['id' => (string) $option->id, 'label' => 'Dịch '.$option->label, 'value' => 'vi-'.$option->value, 'order' => $option->order])->all();
            Language::swap(new LanguageManager);
            $this->post(route('contacts.custom-fields.update', ['custom_field' => $field, 'ref_lang' => 'vi']), [...$payload, 'name' => 'Dịch '.$name, 'options' => $options], $headers)->assertRedirect()->assertSessionHasNoErrors();
        }
        $target = CustomField::query()->where('name', 'Target field')->sole();
        $unrelated = CustomField::query()->where('name', 'Unrelated field')->sole();
        $targetOptions = $target->options()->orderBy('order')->get();
        $keep = $targetOptions[1];
        $removedIds = $action === 'remove-option' ? [$targetOptions[0]->id] : $targetOptions->modelKeys();
        Language::swap(new LanguageManager);
        if ($action === 'delete-field') {
            $this->delete(route('contacts.custom-fields.destroy', $target), [], $headers)->assertRedirect();
            $this->assertModelMissing($target);
            $this->assertDatabaseMissing('contact_custom_fields_translations', ['contact_custom_fields_id' => $target->id]);
        } else {
            $this->post(route('contacts.custom-fields.update', $target), [...$payload, 'name' => 'Target field', 'type' => $action === 'change-type' ? 'text' : 'dropdown', 'options' => $action === 'change-type' ? [] : [['id' => (string) $keep->id, 'label' => $keep->label, 'value' => $keep->value, 'order' => $keep->order]]], $headers)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertModelExists($target);
            $this->assertDatabaseHas('contact_custom_fields_translations', ['contact_custom_fields_id' => $target->id, 'lang_code' => 'vi']);
        }
        foreach ($removedIds as $id) {
            $this->assertDatabaseMissing('contact_custom_field_options', ['id' => $id]);
            $this->assertDatabaseMissing('contact_custom_field_options_translations', ['contact_custom_field_options_id' => $id]);
        }
        if ($action === 'remove-option') {
            $this->assertModelExists($keep);
            $this->assertDatabaseHas('contact_custom_field_options_translations', ['contact_custom_field_options_id' => $keep->id, 'lang_code' => 'vi', 'value' => 'vi-support']);
        }
        $this->assertModelExists($unrelated);
        $this->assertDatabaseHas('contact_custom_fields_translations', ['contact_custom_fields_id' => $unrelated->id, 'lang_code' => 'vi']);
        foreach ($unrelated->options as $option) {
            $this->assertDatabaseHas('contact_custom_field_options_translations', ['contact_custom_field_options_id' => $option->id, 'lang_code' => 'vi']);
        }
    }
}
