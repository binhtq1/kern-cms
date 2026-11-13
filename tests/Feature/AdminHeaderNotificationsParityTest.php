<?php

namespace Tests\Feature;

use Database\Factories\ContactFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Base\Models\AdminNotification;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminHeaderNotificationsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.system' => 'inertia']);
        if (! is_plugin_active('contact')) {
            $this->assertFalse(app(PluginService::class)->activate('contact')['error']);
        }
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_header_exposes_website_notification_badge_and_permission_scoped_contacts(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        AdminNotification::query()->create(['title' => 'Unread notice']);
        ContactFactory::new()->create(['name' => 'Unread sender', 'status' => 'unread']);
        ContactFactory::new()->create(['name' => 'Read sender', 'status' => 'read']);

        $this->get(route('notifications.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.header.website', url('/'))
            ->assertJsonPath('props.admin.header.notifications.count', 1)
            ->assertJsonPath('props.admin.header.notifications.url', route('notifications.index'))
            ->assertJsonCount(1, 'props.admin.header.contacts.items')
            ->assertJsonPath('props.admin.header.contacts.items.0.name', 'Unread sender');
    }

    public function test_header_and_json_list_do_not_leak_inaccessible_notifications_or_contacts(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.index' => true]]));
        AdminNotification::query()->create(['title' => 'Visible notice']);
        AdminNotification::query()->create(['title' => 'Restricted notice', 'permission' => 'users.edit']);
        ContactFactory::new()->create(['name' => 'Private sender', 'status' => 'unread']);

        $this->get(route('notifications.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.header.notifications.count', 1)
            ->assertJsonPath('props.admin.header.contacts', null);
        $this->getJson(route('notifications.index', ['admin_json' => 1]))->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.title', 'Visible notice');
    }

    public function test_contacts_sidebar_badge_only_requires_list_permission(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['contacts.index' => true]]));
        ContactFactory::new()->create(['status' => 'unread']);
        ContactFactory::new()->create(['status' => 'read']);

        $response = $this->get(route('notifications.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.header.contacts', null);
        $navigation = collect($response->json('props.admin.navigation'));
        $contacts = $navigation->first(fn (array $item): bool => collect($item['children'])->contains('url', route('contacts.index')));
        $this->assertNotNull($contacts);
        $this->assertSame(1, $contacts['count']);
    }

    public function test_notification_dialog_supports_pagination_reading_deletion_and_mark_all_read(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $notice = AdminNotification::query()->create(['title' => '<b>New page</b>', 'description' => '<p>Review page</p>', 'action_label' => 'Open page', 'action_url' => route('pages.index')]);
        for ($index = 0; $index < 10; $index++) {
            AdminNotification::query()->create(['title' => 'Notice '.$index]);
        }

        $this->getJson(route('notifications.index', ['admin_json' => 1]))->assertOk()
            ->assertJsonPath('data.count', 11)->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.pagination.lastPage', 2);
        $this->getJson(route('notifications.index', ['admin_json' => 1, 'page' => 2]))->assertOk()->assertJsonCount(1, 'data.items');
        $this->get(route('notifications.read-notification', $notice))->assertRedirect(route('pages.index'));
        $this->assertNotNull($notice->fresh()->read_at);
        $this->putJson(route('notifications.read-all-notification'))->assertOk();
        $this->assertSame(0, AdminNotification::countUnread());
        $this->deleteJson(route('notifications.destroy', $notice))->assertOk();
        $this->assertModelMissing($notice);
        $this->deleteJson(route('notifications.destroy-all-notification'))->assertOk();
        $this->assertSame(0, AdminNotification::query()->count());
    }

    public function test_notification_actions_cannot_read_or_delete_an_inaccessible_notice(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $notice = AdminNotification::query()->create(['title' => 'Private notice', 'permission' => 'users.edit']);

        $this->getJson(route('notifications.read-notification', $notice))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $notice))->assertNotFound();
        $this->assertModelExists($notice);
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_notification_dialog_uses_structured_json_with_legacy_module_flag(): void
    {
        config(['core.base.admin.modules.system' => 'blade']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        AdminNotification::query()->create(['title' => 'Legacy notice']);

        $legacy = $this->getJson(route('notifications.index'))->assertOk();
        $legacy->assertJsonPath('data.items.0.title', 'Legacy notice')->assertJsonStructure(['data' => ['items', 'pagination']]);
        $this->getJson(route('notifications.index', ['admin_json' => 1]))->assertOk()->assertJsonPath('data.items.0.title', 'Legacy notice');
    }

    public function test_notification_dialog_requires_authentication(): void
    {
        $this->getJson(route('notifications.index', ['admin_json' => 1]))->assertUnauthorized();
    }
}
