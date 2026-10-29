<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminFieldGroupSaveContinueTest extends TestCase
{
    use RefreshDatabase;

    public function test_successive_apply_requests_reuse_returned_ids_including_nested_fields_and_deleted_baseline(): void
    {
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        app(PluginService::class)->activate('custom-field');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $field = fn (string $slug, string $type, array $items = []): array => ['id' => null, 'title' => $slug, 'slug' => $slug, 'type' => $type, 'instructions' => '', 'options' => [], 'items' => $items];
        $payload = ['title' => 'Save continue fixture', 'status' => 'published', 'order' => 1, 'rules' => '[]', 'deleted_items' => '[]', 'submitter' => 'apply', 'group_items' => json_encode([$field('acceptance_date', 'date'), $field('acceptance_time', 'time'), $field('repeater', 'repeater', [$field('nested', 'text')])])];
        $this->post(route('custom-fields.store'), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        $group = FieldGroup::query()->sole();
        $edit = route('custom-fields.edit', $group);
        $this->from($edit);
        $response = $this->get($edit, $headers)->assertOk()->assertJsonPath('component', 'FieldGroups/Form');
        $normalize = function (array $items) use (&$normalize): array {
            return array_map(fn (array $item): array => [...$item, 'id' => (string) $item['id'], 'items' => $normalize($item['items'] ?? [])], $items);
        };
        $items = $normalize($response->json('props.group.items'));
        $originalIds = FieldItem::query()->orderBy('id')->pluck('id')->all();
        $this->assertCount(4, $originalIds);
        for ($save = 0; $save < 2; $save++) {
            $this->post(route('custom-fields.update', $group), [...$payload, 'group_items' => json_encode($items)], $headers)->assertRedirect($edit)->assertSessionHasNoErrors();
            $items = $normalize($this->get($edit, $headers)->assertOk()->json('props.group.items'));
            $this->assertSame($originalIds, FieldItem::query()->orderBy('id')->pluck('id')->all());
            $this->assertCount(3, $items);
            $this->assertCount(1, $items[2]['items']);
        }
        $deleted = array_shift($items)['id'];
        $this->post(route('custom-fields.update', $group), [...$payload, 'group_items' => json_encode($items), 'deleted_items' => json_encode([$deleted])], $headers)->assertRedirect($edit)->assertSessionHasNoErrors();
        $items = $normalize($this->get($edit, $headers)->assertOk()->json('props.group.items'));
        $this->post(route('custom-fields.update', $group), [...$payload, 'group_items' => json_encode($items)], $headers)->assertRedirect($edit)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('field_items', 3);
        $this->assertDatabaseMissing('field_items', ['id' => $deleted]);
        $ids = FieldItem::query()->orderBy('id')->pluck('id')->all();
        $this->from($edit)->post(route('custom-fields.update', $group), [...$payload, 'title' => '', 'group_items' => json_encode([...$items, $field('unsaved', 'text')])], $headers)->assertSessionHasErrors('title');
        $this->assertSame($ids, FieldItem::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame('Save continue fixture', $group->fresh()->title);
    }
}
