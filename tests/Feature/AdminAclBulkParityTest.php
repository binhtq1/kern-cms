<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\ACL\Models\Role;
use Kern\ACL\Services\ActivateUserService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminAclBulkParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.users' => 'inertia', 'core.base.admin.modules.roles' => 'inertia']);
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    public function test_user_list_shows_activation_username_super_status_and_filters_completed_activations(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $active = UserFactory::new()->create(['username' => 'active-parity']);
        $inactive = UserFactory::new()->create(['username' => 'inactive-parity']);
        app(ActivateUserService::class)->activate($active);
        $this->actingAs($actor);
        $this->get(route('users.index', ['search' => 'parity', 'status' => 'activated', 'sort' => 'username', 'direction' => 'asc', 'per_page' => 10]), $this->headers())->assertOk()->assertJsonCount(1, 'props.items.data')->assertJsonPath('props.items.data.0.username', $active->username)->assertJsonPath('props.items.data.0.activated', true)->assertJsonPath('props.items.data.0.super_user', false);
        $this->get(route('users.index', ['search' => 'parity', 'status' => 'deactivated']), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.id', $inactive->id);
    }

    public function test_bulk_activate_deactivate_and_delete_use_existing_activation_services(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $users = UserFactory::new()->count(2)->create();
        $ids = $users->modelKeys();
        $this->post(route('users.bulk'), ['ids' => $ids, 'action' => 'status', 'status' => 'activated'], $this->headers())->assertRedirect();
        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->activated);
        }
        $this->post(route('users.bulk'), ['ids' => $ids, 'action' => 'status', 'status' => 'deactivated'], $this->headers())->assertRedirect();
        foreach ($users as $user) {
            $this->assertFalse($user->fresh()->activated);
        }
        $this->post(route('users.bulk'), ['ids' => $ids, 'action' => 'delete'], $this->headers())->assertRedirect();
        foreach ($ids as $id) {
            $this->assertDatabaseMissing('users', ['id' => $id]);
        }
    }

    public function test_forbidden_self_super_and_unknown_ids_leave_entire_batch_unchanged(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['users.index' => true, 'users.edit' => true, 'users.destroy' => true]]);
        $ordinary = UserFactory::new()->create();
        $super = UserFactory::new()->superUser()->create();
        app(ActivateUserService::class)->activate($ordinary);
        app(ActivateUserService::class)->activate($actor);
        $this->actingAs($actor);
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id, $actor->id], 'action' => 'status', 'status' => 'deactivated'], $this->headers())->assertForbidden();
        $this->assertTrue($ordinary->fresh()->activated);
        $this->assertTrue($actor->fresh()->activated);
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id, $super->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id, $actor->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id, 999999], 'action' => 'delete'], $this->headers())->assertSessionHasErrors('ids.1');
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id, $ordinary->id], 'action' => 'delete'], $this->headers())->assertSessionHasErrors('ids.0');
        $this->assertDatabaseHas('users', ['id' => $ordinary->id]);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['users.index' => true]]));
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->post(route('users.bulk'), ['ids' => [$ordinary->id], 'action' => 'status', 'status' => 'activated'], $this->headers())->assertForbidden();
    }

    public function test_role_bulk_deletion_requires_grantable_permissions_and_ignores_blade_module_flag(): void
    {
        $author = UserFactory::new()->superUser()->create();
        $ordinary = Role::query()->create(['created_by' => $author->id, 'updated_by' => $author->id, 'name' => 'Ordinary', 'permissions' => []]);
        $privileged = Role::query()->create(['created_by' => $author->id, 'updated_by' => $author->id, 'name' => 'Privileged', 'permissions' => [ACL_ROLE_SUPER_USER => true]]);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['roles.index' => true, 'roles.destroy' => true]]));
        $this->post(route('roles.bulk'), ['ids' => [$ordinary->id, $privileged->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $ordinary->id]);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['roles.index' => true]]));
        $this->post(route('roles.bulk'), ['ids' => [$ordinary->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.admin.modules.roles' => 'blade']);
        $this->post(route('roles.bulk'), ['ids' => [$ordinary->id], 'action' => 'delete'], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $ordinary->id]);
        config(['core.base.admin.modules.roles' => 'inertia']);
        $this->post(route('roles.bulk'), ['ids' => [$privileged->id], 'action' => 'delete'], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $privileged->id]);
    }

    public function test_setup_picker_urls_are_exposed_only_to_users_with_media_access(): void
    {
        config(['core.base.admin.modules.operations' => 'inertia']);
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));
        $this->get(route('get-started.index'), $this->headers())->assertOk()->assertJsonPath('props.media.list', null)->assertJsonPath('props.media.upload', null);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['media.index' => true]]));
        $this->get(route('get-started.index'), $this->headers())->assertOk()->assertJsonPath('props.media.list', route('media.picker'))->assertJsonPath('props.media.upload', route('media.picker-upload'));
    }
}
