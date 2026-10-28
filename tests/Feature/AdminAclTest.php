<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Kern\ACL\Events\RoleAssignmentEvent;
use Kern\ACL\Events\RoleUpdateEvent;
use Kern\ACL\Forms\UserForm;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\Base\Forms\Fields\TextField;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminAclTest extends TestCase
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
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_acl_reads_require_authentication_and_permissions(): void
    {
        $this->get(route('users.index'), $this->headers())->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('users.index'), $this->headers())->assertForbidden();
        $this->get(route('roles.index'), $this->headers())->assertForbidden();
    }

    public function test_limited_user_can_open_own_profile_from_the_shared_account_menu_link(): void
    {
        $user = UserFactory::new()->create(['permissions' => []]);
        $this->actingAs($user);
        $this->get(route('users.index'), $this->headers())->assertForbidden();

        $this->get(route('users.profile.view', $user), $this->headers())->assertOk()
            ->assertJsonPath('component', 'ACL/UserForm')
            ->assertJsonPath('props.admin.urls.profile', route('users.profile.view', $user))
            ->assertJsonMissingPath('props.admin.user.permissions');
    }

    public function test_role_creator_links_follow_profile_permissions_and_missing_author_fallback(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['roles.index' => true]]);
        $other = UserFactory::new()->create();
        $super = UserFactory::new()->superUser()->create();
        foreach (['Own role' => $actor->getKey(), 'Other role' => $other->getKey(), 'Super role' => $super->getKey(), 'Orphan role' => 0] as $name => $author) {
            Role::query()->create(['name' => $name, 'slug' => (string) str($name)->slug(), 'permissions' => [], 'created_by' => $author, 'updated_by' => $actor->getKey()]);
        }
        $this->actingAs($actor);
        $rows = collect($this->get(route('roles.index'), $this->headers())->assertOk()->json('props.items.data'))->keyBy('name');
        $this->assertSame($actor->url, $rows['Own role']['author_url']);
        $this->assertNull($rows['Other role']['author_url']);
        $this->assertSame($other->name, $rows['Other role']['author']);
        $this->assertNull($rows['Super role']['author_url']);
        $this->assertNull($rows['Orphan role']['author_url']);
        $this->assertSame('', $rows['Orphan role']['author']);
        $this->get($rows['Own role']['author_url'], $this->headers())->assertOk()->assertJsonPath('component', 'ACL/UserForm');
        $actor->permissions = ['roles.index' => true, 'users.edit' => true];
        $actor->save();
        $this->actingAs($actor->fresh());
        $rows = collect($this->get(route('roles.index'), $this->headers())->assertOk()->json('props.items.data'))->keyBy('name');
        $this->assertSame($other->url, $rows['Other role']['author_url']);
        $this->assertNull($rows['Super role']['author_url']);
        $this->get($rows['Other role']['author_url'], $this->headers())->assertOk()->assertJsonPath('component', 'ACL/UserForm');
        $this->actingAs($super);
        $rows = collect($this->get(route('roles.index'), $this->headers())->assertOk()->json('props.items.data'))->keyBy('name');
        $this->assertSame($other->url, $rows['Other role']['author_url']);
        $this->assertSame($super->url, $rows['Super role']['author_url']);
    }

    public function test_users_roles_and_profile_are_typed_and_never_expose_passwords(): void
    {
        $user = UserFactory::new()->superUser()->create();
        $this->actingAs($user);
        $this->get(route('users.index'), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/Index')->assertJsonPath('props.kind', 'users');
        $this->get(route('users.create'), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/UserForm');
        $this->get(route('users.profile.view', $user), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/UserForm')->assertJsonMissingPath('props.user.password')->assertJsonMissingPath('props.user.permissions')->assertJsonMissingPath('props.user.remember_token');
        $this->get(route('roles.index'), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/Index')->assertJsonPath('props.kind', 'roles');
        $this->get(route('roles.create'), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/RoleForm');
    }

    public function test_user_creation_preserves_service_events_and_blocks_mass_assignment(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $role = Role::query()->create(['name' => 'Editor', 'created_by' => $actor->id, 'updated_by' => $actor->id, 'permissions' => ['pages.index' => true]]);
        Event::fake([RoleAssignmentEvent::class]);
        $this->actingAs($actor)->post(route('users.store'), [
            'first_name' => 'New', 'last_name' => 'Editor', 'username' => 'new-editor', 'email' => 'new@example.test',
            'password' => 'test-password', 'password_confirmation' => 'test-password', 'role_id' => $role->getKey(),
            'permissions' => [ACL_ROLE_SUPER_USER => true], 'avatar_id' => 123, 'super_user' => 1,
        ], $this->headers())->assertRedirect();
        $user = User::query()->where('username', 'new-editor')->firstOrFail();
        $this->assertTrue(Hash::check('test-password', $user->password));
        $this->assertFalse((bool) $user->super_user);
        $this->assertEmpty($user->permissions);
        $this->assertNull($user->avatar_id);
        $this->assertTrue($user->roles()->whereKey($role->getKey())->exists());
        Event::assertDispatched(RoleAssignmentEvent::class);
    }

    public function test_profile_password_preferences_and_validation_use_existing_flows(): void
    {
        $user = UserFactory::new()->superUser()->create(['password' => 'old-password']);
        $this->actingAs($user)->from(route('users.profile.view', $user));
        $this->put(route('users.update-profile', $user), [], $this->headers())->assertSessionHasErrors(['first_name', 'last_name', 'username', 'email']);
        $this->put(route('users.update-profile', $user), ['first_name' => 'Updated', 'last_name' => 'Name', 'username' => 'updated-name', 'email' => 'updated@example.test', 'permissions' => ['injected' => true]], $this->headers())->assertRedirect();
        $this->assertSame('Updated', $user->fresh()->first_name);
        $this->assertArrayNotHasKey('injected', $user->fresh()->permissions ?? []);
        $this->put(route('users.change-password', $user), ['old_password' => 'incorrect', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $this->headers())->assertSessionHasErrors('old_password');
        $this->put(route('users.change-password', $user), ['old_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $this->headers())->assertRedirect();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->put(route('users.update-preferences', $user), ['locale' => 'en', 'locale_direction' => 'rtl', 'theme_mode' => 'dark'], $this->headers())->assertRedirect();
        $this->assertSame('dark', $user->fresh()->getMeta('theme_mode'));
        $this->assertSame('rtl', $user->fresh()->getMeta('locale_direction'));
    }

    public function test_roles_save_update_duplicate_assign_and_delete(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $user = UserFactory::new()->create();
        Event::fake([RoleUpdateEvent::class, RoleAssignmentEvent::class]);
        $this->actingAs($actor)->post(route('roles.store'), ['name' => 'Editors', 'description' => 'Content team', 'is_default' => 1, 'flags' => ['pages.index']], $this->headers())->assertRedirect();
        $role = Role::query()->where('name', 'Editors')->firstOrFail();
        $this->get(route('roles.edit', $role), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/RoleForm');
        $this->post(route('roles.update', $role), ['name' => 'Editors updated', 'is_default' => 0, 'flags' => ['pages.index', 'pages.edit']], $this->headers())->assertRedirect();
        Event::assertDispatched(RoleUpdateEvent::class);
        $this->post(route('roles.duplicate', $role), $this->headers())->assertRedirect();
        $this->assertDatabaseHas('roles', ['name' => 'Editors updated (Duplicate)']);
        $this->post(route('roles.assign'), ['pk' => $user->getKey(), 'value' => $role->getKey()], $this->headers())->assertRedirect();
        Event::assertDispatched(RoleAssignmentEvent::class);
        $this->assertTrue($user->roles()->whereKey($role->getKey())->exists());
        $this->delete(route('roles.destroy', $role), [], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $role->getKey()]);
        $this->assertDatabaseMissing('role_users', ['role_id' => $role->getKey()]);
    }

    public function test_acl_cannot_escalate_through_roles_or_modify_protected_users(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['users.index' => true, 'users.create' => true, 'users.edit' => true, 'roles.create' => true, 'roles.edit' => true, 'roles.destroy' => true, 'pages.index' => true]]);
        $super = UserFactory::new()->superUser()->create();
        $role = Role::query()->create(['name' => 'Privileged', 'created_by' => $actor->id, 'updated_by' => $actor->id, 'permissions' => ['users.destroy' => true]]);
        $this->actingAs($actor);
        $this->post(route('roles.store'), ['name' => 'Escalation', 'flags' => ['users.destroy']], $this->headers())->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Unknown flag', 'flags' => ['unknown.permission']], $this->headers())->assertSessionHasErrors('flags');
        $this->post(route('roles.assign'), ['pk' => $actor->getKey(), 'value' => $role->getKey()], $this->headers())->assertForbidden();
        $this->post(route('roles.duplicate', $role), $this->headers())->assertForbidden();
        $this->delete(route('roles.destroy', $role), [], $this->headers())->assertForbidden();
        $this->put(route('users.update-profile', $super), ['first_name' => 'Taken', 'last_name' => 'Over', 'username' => 'taken-over', 'email' => 'taken@example.test'], $this->headers())->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Escalation']);
    }

    public function test_password_reset_for_another_user_keeps_the_current_administrator(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $other = UserFactory::new()->create();
        $this->actingAs($actor)->put(route('users.change-password', $other), ['password' => 'reset-password', 'password_confirmation' => 'reset-password'], $this->headers())->assertRedirect();
        $this->assertAuthenticatedAs($actor);
        $this->assertTrue(Hash::check('reset-password', $other->fresh()->password));
    }

    public function test_acl_renderer_flag_is_ignored_and_ajax_remains_json(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        config(['core.base.admin.modules.users' => 'blade']);
        $this->get(route('users.index'), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/Index');
        $this->get(route('roles.index'), $this->headers())->assertOk()->assertJsonPath('component', 'ACL/Index');
        $this->postJson(route('roles.store'), ['name' => 'AJAX role', 'flags' => ['pages.index']])->assertOk()->assertJsonPath('error', false);
    }

    public function test_unknown_acl_form_fields_are_blocked_before_mutation(): void
    {
        UserForm::extend(fn (UserForm $form) => $form->add('unsupported_extra', TextField::class));
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('users.create'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->post(route('users.store'), ['username' => 'blocked'], $this->headers())->assertStatus(503);
        $this->assertDatabaseMissing('users', ['username' => 'blocked']);
    }

    public function test_schema_html_extensions_are_detected_and_replayed_once_for_blade(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $calls = 0;
        UserForm::beforeRendering(function () use (&$calls): void {
            $calls++;
            echo '<div data-legacy-extension="true">Legacy form content</div>';
        });
        view()->share('errors', new ViewErrorBag);
        $form = UserForm::create()->template('core/base::forms.form-no-wrap');
        $form->prepareForSchema()->prepareForSchema();
        $this->assertTrue($form->hasSchemaRenderingOutput());
        $this->assertSame(1, $calls);
        $this->assertStringContainsString('data-legacy-extension="true"', $form->renderForm());
        $this->assertFalse($form->hasSchemaRenderingOutput());
        $this->assertSame(1, $calls);
    }

    public function test_super_user_mutations_require_post_delete_and_protect_self_deletion(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $other = UserFactory::new()->create();
        $this->actingAs($actor);
        $this->get(route('users.make-super', $other))->assertStatus(405);
        $this->assertFalse((bool) $other->fresh()->super_user);
        $this->post(route('users.make-super', $other), [], $this->headers())->assertRedirect();
        $this->assertTrue((bool) $other->fresh()->super_user);
        $this->get(route('users.remove-super', $other))->assertStatus(405);
        $this->assertTrue((bool) $other->fresh()->super_user);
        $this->delete(route('users.remove-super', $other), [], $this->headers())->assertRedirect();
        $this->assertFalse((bool) $other->fresh()->super_user);
        $this->delete(route('users.destroy', $actor), [], $this->headers())->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $actor->getKey()]);
    }
}
