<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Kern\Slug\Models\Slug;
use Kern\Theme\Events\RenderingThemeOptionSettings;
use Kern\Theme\Facades\ThemeOption;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Kern\Theme\Supports\ThemeSupport;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminThemeOptionParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.theme-options' => 'inertia']);
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->actingAs(UserFactory::new()->superUser()->create());
        app('events')->listen(RenderingThemeOptionSettings::class, function (): void {
            ThemeOption::setField(['id' => 'download_file', 'section_id' => 'opt-text-subsection-general', 'type' => 'mediaFile', 'label' => 'Download file', 'attributes' => ['name' => 'download_file']]);
            ThemeOption::setField(['id' => 'parity_rows', 'section_id' => 'opt-text-subsection-general', 'type' => 'repeater', 'label' => 'Rows', 'attributes' => ['name' => 'parity_rows', 'fields' => [
                ['type' => 'email', 'label' => 'Email', 'attributes' => ['name' => 'email', 'value' => 'default@example.test']],
                ['type' => 'select', 'label' => 'Style', 'attributes' => ['name' => 'style', 'value' => 'compact', 'choices' => ['compact' => 'Compact', 'wide' => 'Wide']]],
            ]]]);
        });
    }

    protected function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_document_picker_and_repeater_defaults_preserve_field_contracts(): void
    {
        ThemeOption::setOption('email', 'unrelated@example.test');
        ThemeOption::saveOptions();
        $response = $this->get(route('theme.options'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Theme/Options');
        $fields = collect($response->json('props.sections'))->flatMap(fn (array $section): array => $section['fields'])->keyBy('name');
        $this->assertSame('file', $fields['download_file']['type']);
        $this->assertSame('default@example.test', $fields['parity_rows']['children'][0]['value']);
        $this->assertSame('select', $fields['parity_rows']['children'][1]['type']);
        $this->assertSame($fields['date_format']['choices'][0]['value'], $fields['date_format']['value']);
        $this->post(route('theme.options.post'), ['date_format' => $fields['date_format']['value']], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_disabled_and_readonly_theme_options_keep_saved_values_while_editable_fields_save(): void
    {
        app('events')->listen(RenderingThemeOptionSettings::class, function (): void {
            foreach (['disabled', 'readonly'] as $attribute) {
                ThemeOption::setField(['id' => 'locked_'.$attribute, 'section_id' => 'opt-text-subsection-general', 'type' => 'text', 'attributes' => ['name' => 'locked_'.$attribute, $attribute => true, 'value' => 'Default value']]);
            }
            ThemeOption::setField(['id' => 'locked_rows', 'section_id' => 'opt-text-subsection-general', 'type' => 'repeater', 'attributes' => ['name' => 'locked_rows', 'fields' => [
                ['type' => 'text', 'attributes' => ['name' => 'disabled_value', 'disabled' => true, 'value' => 'Disabled default']],
                ['type' => 'text', 'attributes' => ['name' => 'readonly_value', 'readonly' => true, 'value' => 'Readonly default']],
                ['type' => 'text', 'attributes' => ['name' => 'editable_value', 'value' => 'Editable default']],
            ]]]);
        });
        ThemeOption::setOption('locked_disabled', 'Stored disabled');
        ThemeOption::setOption('locked_readonly', 'Stored readonly');
        ThemeOption::setOption('locked_rows', json_encode([
            [['key' => 'disabled_value', 'value' => 'Stored disabled child'], ['key' => 'readonly_value', 'value' => 'Stored readonly child'], ['key' => 'editable_value', 'value' => 'Previous editable']],
            [['key' => 'disabled_value', 'value' => 'Second disabled child'], ['key' => 'readonly_value', 'value' => 'Second readonly child'], ['key' => 'editable_value', 'value' => 'Second editable']],
        ]));
        ThemeOption::saveOptions();
        $rows = [[['key' => 'disabled_value', 'value' => 'Changed disabled'], ['key' => 'readonly_value', 'value' => 'Changed readonly'], ['key' => 'editable_value', 'value' => 'Updated editable']]];
        $rows[] = $rows[0];
        $rows[] = $rows[0];

        $this->post(route('theme.options.post'), ['locked_disabled' => 'Changed', 'locked_readonly' => 'Changed', 'locked_rows' => $rows, 'download_file' => 'documents/updated.pdf'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Stored disabled', ThemeOption::getOption('locked_disabled'));
        $this->assertSame('Stored readonly', ThemeOption::getOption('locked_readonly'));
        $saved = json_decode(ThemeOption::getOption('locked_rows'), true);
        $this->assertSame('Stored disabled child', $saved[0][0]['value']);
        $this->assertSame('Stored readonly child', $saved[0][1]['value']);
        $this->assertSame('Updated editable', $saved[0][2]['value']);
        $this->assertSame('Second disabled child', $saved[1][0]['value']);
        $this->assertSame('Second readonly child', $saved[1][1]['value']);
        $this->assertSame('Updated editable', $saved[1][2]['value']);
        $this->assertSame('Disabled default', $saved[2][0]['value']);
        $this->assertSame('Readonly default', $saved[2][1]['value']);
        $this->assertSame('documents/updated.pdf', ThemeOption::getOption('download_file'));
    }

    public function test_legacy_social_rows_are_keyed_normalized_and_save_one_color_without_losing_other_links(): void
    {
        $legacy = ThemeSupport::getDefaultSocialLinksData();
        $legacy[1] = array_reverse($legacy[1]);
        ThemeOption::setOption('social_links', json_encode($legacy));
        ThemeOption::saveOptions();
        $read = fn () => collect($this->get(route('theme.options', ['id' => 'opt-text-subsection-social-links']), $this->inertiaHeaders())->assertOk()->json('props.sections'))->flatMap(fn (array $section): array => $section['fields'])->firstWhere('name', 'social_links');
        $field = $read();
        $this->assertCount(6, $field['value'][0]);
        $this->assertSame(array_column($field['children'], 'name'), array_column($field['value'][1], 'key'));
        $values = array_map(fn (array $row): array => array_column($row, 'value', 'key'), $field['value']);
        $this->assertSame('X (Twitter)', $values[1]['name']);
        $this->assertSame('https://x.com', $values[1]['url']);
        $rows = $field['value'];
        foreach ($rows[0] as &$entry) {
            if ($entry['key'] === 'color') {
                $entry['value'] = '#123456';
            }
        }
        unset($entry);
        $this->post(route('theme.options.post'), ['social_links' => $rows], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $expected = array_map(fn (array $row): array => array_map(fn (array $entry): array => ['key' => $entry['key'], 'value' => $entry['value'] === '' ? null : $entry['value']], $row), $rows);
        $this->assertSame($expected, $read()['value']);
        $links = ThemeSupport::getSocialLinks();
        $this->assertCount(count($legacy), $links);
        $this->assertSame('#123456', $links[0]->getColor());
        $this->assertSame('https://x.com', $links[1]->getUrl());
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $page = PageFactory::new()->create(['name' => 'Social public fixture', 'status' => 'published']);
        Slug::query()->create(['key' => 'social-public-fixture', 'reference_id' => $page->id, 'reference_type' => $page::class, 'prefix' => '']);
        $this->get('/social-public-fixture')->assertOk()->assertSee('href="https://x.com"', false)->assertSee('color: #123456 !important;', false);
        $this->post(route('theme.options.post'), ['social_links' => $legacy], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $saved = ThemeOption::getOption('social_links');
        foreach ([
            [[['key' => 'forged', 'value' => 'bad']]],
            [[['key' => 'name', 'value' => 'First'], ['key' => 'name', 'value' => 'Duplicate']]],
            ['invalid row'],
            [['name' => 'associative row']],
            [[['key' => 'name', 'value' => 'Name', 'extra' => 'smuggled']]],
        ] as $invalid) {
            $this->post(route('theme.options.post'), ['social_links' => $invalid], $this->inertiaHeaders())->assertSessionHasErrors('social_links');
            $this->assertSame($saved, ThemeOption::getOption('social_links'));
        }
        $invalidColor = json_decode($saved, true);
        $colorIndex = array_search('color', array_column($invalidColor[0], 'key'), true);
        $invalidColor[0][$colorIndex]['value'] = 'red';
        $this->post(route('theme.options.post'), ['social_links' => $invalidColor], $this->inertiaHeaders())->assertSessionHasErrors('social_links.0.'.$colorIndex.'.value');
        $this->assertSame($saved, ThemeOption::getOption('social_links'));
    }

    public function test_nested_theme_fields_validate_types_and_reject_malformed_rows_before_saving(): void
    {
        $rows = [[['key' => 'email', 'value' => 'contact@example.test'], ['key' => 'style', 'value' => 'wide']]];
        $this->post(route('theme.options.post'), ['parity_rows' => $rows, 'download_file' => 'documents/guide.pdf'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($rows, json_decode(ThemeOption::getOption('parity_rows'), true));
        $this->assertSame('documents/guide.pdf', ThemeOption::getOption('download_file'));
        $invalid = [[['key' => 'email', 'value' => 'invalid'], ['key' => 'style', 'value' => 'unknown']]];
        $this->post(route('theme.options.post'), ['parity_rows' => $invalid], $this->inertiaHeaders())->assertSessionHasErrors(['parity_rows.0.0.value', 'parity_rows.0.1.value']);
        $this->post(route('theme.options.post'), ['parity_rows' => 'broken-json'], $this->inertiaHeaders())->assertSessionHasErrors('parity_rows');
        $rows[0][0]['unexpected'] = 'ignored';
        $this->post(route('theme.options.post'), ['parity_rows' => $rows], $this->inertiaHeaders())->assertSessionHasErrors('parity_rows');
        $this->assertSame('contact@example.test', json_decode(ThemeOption::getOption('parity_rows'), true)[0][0]['value']);
    }
}
