<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\CustomField\Actions\ExportCustomFieldsAction;
use Kern\CustomField\Actions\ImportCustomFieldsAction;
use Kern\CustomField\Facades\CustomField as CustomFieldFacade;
use Kern\CustomField\Forms\CustomFieldForm;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\CustomField\Support\CustomFieldSupport;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminFieldGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        app(PluginService::class)->activate('custom-field');
        config(['core.base.admin.modules.field-groups' => 'inertia']);
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    protected function payload(string $title = 'Details'): array
    {
        $child = ['id' => null, 'title' => 'Caption', 'slug' => 'caption', 'instructions' => '', 'type' => 'text', 'options' => ['defaultValue' => 'Caption default'], 'items' => []];

        return ['title' => $title, 'status' => 'published', 'order' => 1, 'rules' => '[]', 'group_items' => json_encode([['id' => null, 'title' => 'Slides', 'slug' => 'slides', 'instructions' => '', 'type' => 'repeater', 'options' => ['buttonLabel' => 'Add slide'], 'items' => [$child]]]), 'deleted_items' => '[]', 'submitter' => 'save'];
    }

    public function test_builder_saves_nested_fields_and_updates_owned_fields_with_order_and_options(): void
    {
        $this->get(route('custom-fields.create'), $this->headers())->assertOk()->assertJsonPath('component', 'FieldGroups/Form')->assertJsonStructure(['props' => ['types', 'ruleSchema']]);
        $this->post(route('custom-fields.store'), $this->payload(), $this->headers())->assertRedirect(route('custom-fields.index'));
        $group = FieldGroup::query()->firstOrFail();
        $response = $this->get(route('custom-fields.edit', $group), $this->headers())->assertOk()->assertJsonPath('props.group.items.0.items.0.title', 'Caption');
        $items = $response->json('props.group.items');
        $items[0]['id'] = (string) $items[0]['id'];
        $items[0]['items'][0]['id'] = (string) $items[0]['items'][0]['id'];
        $items[0]['items'][0]['title'] = 'Updated caption';
        $this->post(route('custom-fields.update', $group), [...$this->payload(), 'group_items' => json_encode($items)], $this->headers())->assertRedirect();
        $this->assertDatabaseHas('field_items', ['field_group_id' => $group->getKey(), 'title' => 'Updated caption', 'parent_id' => $items[0]['id']]);
        $this->assertSame('Caption default', json_decode(FieldItem::query()->where('title', 'Updated caption')->firstOrFail()->options, true)['defaultValue']);
        $this->get(route('custom-fields.index'), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1);
        $this->post(route('custom-fields.update', $group), [...$this->payload(), 'group_items' => '[]', 'deleted_items' => json_encode([$items[0]['id']])], $this->headers())->assertRedirect();
        $this->assertDatabaseCount('field_items', 0);
    }

    public function test_field_ownership_and_invalid_rules_reject_changes_before_writes(): void
    {
        $this->post(route('custom-fields.store'), $this->payload('First'), $this->headers())->assertRedirect();
        $first = FieldGroup::query()->firstOrFail();
        $this->post(route('custom-fields.store'), $this->payload('Second'), $this->headers())->assertRedirect();
        $second = FieldGroup::query()->whereKeyNot($first->getKey())->firstOrFail();
        $foreign = (string) $first->fieldItems()->firstOrFail()->getKey();
        $this->post(route('custom-fields.update', $second), [...$this->payload('Injected'), 'deleted_items' => json_encode([$foreign])], $this->headers())->assertSessionHasErrors('deleted_items');
        $this->assertDatabaseHas('field_items', ['id' => $foreign]);
        $this->assertDatabaseHas('field_groups', ['id' => $second->getKey(), 'title' => 'Second']);
        $this->post(route('custom-fields.update', $second), [...$this->payload(), 'rules' => json_encode([[['name' => 'unknown', 'type' => '==', 'value' => 'unknown']]])], $this->headers())->assertSessionHasErrors();
        $this->assertDatabaseCount('field_groups', 2);
    }

    public function test_import_export_roundtrip_and_invalid_nested_import_are_atomic(): void
    {
        $this->post(route('custom-fields.store'), $this->payload(), $this->headers())->assertRedirect();
        $export = app(ExportCustomFieldsAction::class)->run([])['data'];
        $this->post(route('custom-fields.import'), ['json_data' => $export], $this->headers())->assertRedirect(route('custom-fields.index'));
        $this->assertDatabaseCount('field_groups', 2);
        $this->assertDatabaseCount('field_items', 4);
        $invalid = $export;
        $invalid[0]['items'][0]['children'][0]['type'] = 'unknown';
        $this->post(route('custom-fields.import'), ['json_data' => [...$export, ...$invalid]], $this->headers())->assertSessionHasErrors();
        $this->assertDatabaseCount('field_groups', 2);
    }

    public function test_unknown_builder_extensions_are_blocked_and_import_action_rolls_back_nested_errors(): void
    {
        $result = app(ImportCustomFieldsAction::class)->run([['title' => 'Invalid nested import', 'status' => 'published', 'order' => 1, 'rules' => '[]', 'items' => [['title' => 'Repeater', 'slug' => 'repeater', 'type' => 'repeater', 'order' => 1, 'options' => '{}', 'children' => [['title' => '', 'slug' => 'child', 'type' => 'text', 'order' => 1]]]]]]);
        $this->assertTrue($result['error']);
        $this->assertDatabaseCount('field_groups', 0);
        $this->assertDatabaseCount('field_items', 0);
        CustomFieldForm::beforeRendering(fn ($form) => $form->add('legacy_widget', HtmlField::class));
        $this->get(route('custom-fields.create'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->post(route('custom-fields.store'), $this->payload(), $this->headers())->assertStatus(503);
        $this->assertDatabaseCount('field_groups', 0);
    }

    public function test_builder_stays_react_when_module_flag_is_blade_and_permissions_are_enforced(): void
    {
        config(['core.base.admin.modules.field-groups' => 'blade']);
        $this->get(route('custom-fields.index'), $this->headers())->assertOk()->assertJsonPath('component', 'FieldGroups/Index');
        $this->get(route('custom-fields.create'), $this->headers())->assertOk()->assertJsonPath('component', 'FieldGroups/Form');
        config(['core.base.admin.modules.field-groups' => 'inertia']);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['custom-fields.index' => true]]));
        $this->post(route('custom-fields.store'), $this->payload(), $this->headers())->assertForbidden();
        $this->assertDatabaseCount('field_groups', 0);
    }

    public function test_import_rejects_scalar_groups_and_malformed_status_or_nested_fields_without_partial_writes(): void
    {
        $valid = ['title' => 'Valid import', 'status' => 'published', 'order' => 1, 'rules' => '[]', 'items' => []];
        $invalidGroups = [
            'oops',
            [...$valid, 'status' => []],
            [...$valid, 'status' => ['label' => 'Published']],
            [...$valid, 'status' => ['value' => []]],
            [...$valid, 'items' => ['oops']],
            [...$valid, 'items' => [['title' => 'Nested', 'type' => 'repeater', 'children' => 'oops']]],
            [...$valid, 'items' => [['title' => 'Nested', 'type' => 'text', 'options' => 123]]],
            [...$valid, 'rules' => '[["oops"]]'],
        ];
        foreach ($invalidGroups as $invalid) {
            $this->postJson(route('custom-fields.import'), ['json_data' => [$valid, $invalid]])->assertUnprocessable();
            $this->assertDatabaseCount('field_groups', 0);
            $this->assertDatabaseCount('field_items', 0);
        }
        $this->post(route('custom-fields.import'), ['json_data' => [[...$valid, 'status' => ['value' => 'published', 'label' => 'Published']]]], $this->headers())->assertRedirect(route('custom-fields.index'));
        $this->assertDatabaseHas('field_groups', ['title' => 'Valid import', 'status' => 'published']);
    }

    public function test_rule_schema_can_resolve_dynamic_template_choices_repeatedly_without_consuming_definitions(): void
    {
        $support = new CustomFieldSupport;
        $support->registerRule('basic', 'Page template', 'page_template', fn (): array => ['default' => 'Default', 'ripple' => 'Ripple fixture template']);
        $first = $support->getRuleSchema();
        $this->assertSame(['default' => 'Default', 'ripple' => 'Ripple fixture template'], $first['basic']['items']['page_template']['data']);
        $support->renderRules();
        $this->assertSame($first, $support->getRuleSchema());
    }

    public function test_field_group_form_and_save_keep_page_template_choices_after_fallback_schema_preparation(): void
    {
        $response = $this->get(route('custom-fields.create'), $this->headers())->assertOk();
        $schema = $response->json('props.ruleSchema');
        $this->assertArrayHasKey('default', $schema['basic']['items']['page_template']['data']);
        CustomFieldFacade::renderRules();
        $this->assertArrayHasKey('default', CustomFieldFacade::getRuleSchema()['basic']['items']['page_template']['data']);
        $rules = [[['name' => 'page_template', 'type' => '==', 'value' => 'default']]];
        $this->post(route('custom-fields.store'), [...$this->payload('Page template group'), 'rules' => json_encode($rules)], $this->headers())
            ->assertRedirect(route('custom-fields.index'))->assertSessionHasNoErrors();
        $group = FieldGroup::query()->sole();
        $this->assertSame($rules, json_decode($group->rules, true));
    }

    public function test_default_post_format_display_rule_saves_and_imports_but_invalid_empty_values_do_not(): void
    {
        app(PluginService::class)->activate('blog');
        $response = $this->get(route('custom-fields.create'), $this->headers())->assertOk();
        $schema = collect($response->json('props.ruleSchema'))->flatMap(fn (array $group): array => $group['items'])->keyBy('slug');
        $name = 'kern_blog_models_post_post_format';
        $this->assertArrayHasKey('', $schema[$name]['data']);
        $rule = ['name' => $name, 'type' => '==', 'value' => ''];
        $rules = [[$rule]];
        $this->post(route('custom-fields.store'), [...$this->payload('Default post format'), 'group_items' => '[]', 'rules' => json_encode($rules)], $this->headers())
            ->assertRedirect(route('custom-fields.index'))->assertSessionHasNoErrors();
        $group = FieldGroup::query()->sole();
        $this->assertSame($rules, json_decode($group->rules, true));
        $edit = route('custom-fields.edit', $group);
        $this->get($edit, $this->headers())->assertOk()->assertJsonPath('props.group.rules.0.0.value', '');
        $rules = [[[...$rule, 'type' => '!=']]];
        $this->from($edit)->post(route('custom-fields.update', $group), [...$this->payload('Default post format'), 'group_items' => '[]', 'rules' => json_encode($rules), 'submitter' => 'apply'], $this->headers())
            ->assertRedirect($edit)->assertSessionHasNoErrors();
        $this->get($edit, $this->headers())->assertOk()->assertJsonPath('props.group.rules', $rules);
        $export = app(ExportCustomFieldsAction::class)->run([])['data'];
        $this->post(route('custom-fields.import'), ['json_data' => $export], $this->headers())->assertRedirect(route('custom-fields.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('field_groups', 2);
        foreach (FieldGroup::query()->get() as $saved) {
            $this->assertSame($rules, json_decode($saved->rules, true));
        }
        $invalidRules = [
            ['name' => $name, 'type' => '=='],
            [...$rule, 'value' => null],
            [...$rule, 'value' => []],
            [...$rule, 'value' => 'unknown'],
            [...$rule, 'name' => 'page_template'],
            [...$rule, 'name' => 'unknown'],
            [...$rule, 'type' => 'unknown'],
            [...$rule, 'value' => str_repeat('x', 251)],
        ];
        foreach ($invalidRules as $invalid) {
            $this->post(route('custom-fields.store'), [...$this->payload('Invalid display rule'), 'rules' => json_encode([[$invalid]])], $this->headers())->assertSessionHasErrors();
            $badImport = $export;
            $badImport[0]['rules'] = json_encode([[$invalid]]);
            $this->post(route('custom-fields.import'), ['json_data' => $badImport], $this->headers())->assertSessionHasErrors();
            $this->assertDatabaseCount('field_groups', 2);
        }
    }
}
