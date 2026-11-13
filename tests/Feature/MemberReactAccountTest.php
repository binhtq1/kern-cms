<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Kern\ACL\Models\User;
use Kern\Blog\Models\Post;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Member\Http\Middleware\HandleMemberInertiaRequests;
use Kern\Member\Models\Member;
use Kern\Member\Models\MemberActivityLog;
use Kern\Member\Supports\MemberPostData;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MemberReactAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'mail.default' => 'array']);
        foreach (['blog', 'member', 'custom-field'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        setting()->set(['theme' => 'ripple', 'member_enabled_login' => '1', 'verify_account_email' => '0'])->save();
        Theme::setThemeName('ripple');
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        require plugin_path('member/routes/member.php');
        Route::getRoutes()->refreshNameLookups();
    }

    private function member(string $email = 'member@example.test'): Member
    {
        return Member::query()->forceCreate(['first_name' => 'Test', 'last_name' => 'Member', 'email' => $email, 'password' => 'member-password', 'confirmed_at' => now()]);
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => (new HandleMemberInertiaRequests)->version(request()) ?? ''];
    }

    public function test_account_pages_use_a_separate_react_shell_and_do_not_expose_admin_data(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'member');
        foreach (['public.member.dashboard' => 'Dashboard', 'public.member.settings' => 'Settings', 'public.member.posts.index' => 'Posts', 'public.member.posts.create' => 'PostForm'] as $route => $component) {
            $response = $this->get(route($route))->assertOk()->assertViewIs('plugins/member::themes.dashboard.react');
            $response->assertSee('data-page=', false)->assertDontSee('jquery', false)->assertDontSee('/vendor/core/', false);
            $page = $this->get(route($route), $this->headers())->assertOk()->assertJsonPath('component', $component)->assertJsonPath('props.member.id', (string) $member->id);
            $page->assertJsonMissingPath('props.admin.session')->assertJsonMissingPath('props.admin.permissions')->assertJsonMissingPath('props.member.password');
            $this->assertStringNotContainsString('/admin/media', $page->getContent());
            $page->assertJsonPath('props.memberUrls.dashboard', route('public.member.dashboard'));
            if ($route === 'public.member.posts.create') {
                $page->assertJsonPath('props.urls.submit', route('public.member.posts.store'));
            }
        }
    }

    public function test_guest_and_admin_authentication_cannot_access_member_account_pages(): void
    {
        $this->get(route('public.member.dashboard'))->assertRedirect(route('public.member.login'));
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->getJson(route('public.member.posts.index'))->assertUnauthorized();
    }

    public function test_post_lists_and_mutations_are_scoped_by_author_id_and_author_type(): void
    {
        $member = $this->member();
        $other = $this->member('other@example.test');
        $own = Post::query()->create(['name' => 'Own post', 'author_id' => $member->id, 'author_type' => Member::class, 'status' => 'draft']);
        $foreign = Post::query()->create(['name' => 'Other post', 'author_id' => $other->id, 'author_type' => Member::class, 'status' => 'draft']);
        $admin = Post::query()->create(['name' => 'Admin ID collision', 'author_id' => $member->id, 'author_type' => User::class, 'status' => 'published']);
        $this->actingAs($member, 'member');
        $this->get(route('public.member.posts.index', ['search' => 'Own', 'sort' => 'name', 'direction' => 'asc']), $this->headers())->assertOk()->assertJsonPath('props.posts.total', 1)->assertJsonPath('props.posts.data.0.id', (string) $own->id);
        foreach ([$foreign, $admin] as $post) {
            $this->get(route('public.member.posts.edit', $post))->assertNotFound();
            $this->postJson(route('public.member.posts.update', $post), ['name' => 'Attack'])->assertNotFound();
            $this->deleteJson(route('public.member.posts.destroy', $post))->assertNotFound();
        }
        $this->postJson(route('public.member.posts.update', $own), ['name' => 'Updated', 'author_id' => $other->id, 'author_type' => User::class, 'status' => 'published', 'tag' => '[]'])->assertOk()->assertJsonPath('error', false);
        $this->assertSame($member->id, $own->fresh()->author_id);
        $this->assertSame(Member::class, $own->fresh()->author_type);
        $this->assertSame('draft', $own->fresh()->status->getValue());
    }

    public function test_new_posts_remain_pending_and_unknown_custom_field_groups_are_rejected(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'member');
        $this->postJson(route('public.member.posts.store'), ['name' => 'New post', 'status' => 'published', 'tag' => '[]'])->assertOk()->assertJsonPath('error', false);
        $post = Post::query()->where('name', 'New post')->sole();
        $this->assertSame('pending', $post->status->getValue());
        $this->assertSame($member->id, $post->author_id);
        $this->postJson(route('public.member.posts.store'), ['name' => 'Forged field', 'custom_fields' => json_encode([['id' => 99999, 'items' => []]])])->assertUnprocessable()->assertJsonValidationErrors('custom_fields');
        $this->assertDatabaseMissing('posts', ['name' => 'Forged field']);
    }

    public function test_member_create_form_values_can_be_submitted_without_privileged_fields(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'member');
        $page = $this->get(route('public.member.posts.create'), $this->headers())->assertOk();
        $values = $page->json('props.values');
        foreach (['status', 'is_featured', 'author_id', 'author_type'] as $field) {
            $this->assertArrayNotHasKey($field, $values);
        }

        $payload = [
            ...$values,
            'name' => 'Member browser payload',
            'description' => 'Created from the actual serialized form values.',
            'content' => '<p>Member editor content.</p>',
            'tag' => '[]',
            'submitter' => 'apply',
            '_method' => 'POST',
        ];
        $this->postJson($page->json('props.urls.submit'), $payload)->assertOk()->assertJsonPath('error', false);
        $post = Post::query()->where('name', $payload['name'])->sole();
        $this->assertSame('pending', $post->status->getValue());
        $this->assertSame($member->id, $post->author_id);
        $this->assertSame(Member::class, $post->author_type);
        $this->assertSame($payload['content'], $post->content);

        $edit = $this->get(route('public.member.posts.edit', $post), $this->headers())->assertOk();
        foreach (['status', 'is_featured', 'author_id', 'author_type'] as $field) {
            $this->assertArrayNotHasKey($field, $edit->json('props.values'));
        }
    }

    public function test_custom_fields_are_serialized_and_saved_without_an_administrator_role(): void
    {
        $member = $this->member();
        $group = FieldGroup::query()->create(['title' => 'Post details', 'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Post::class]]]), 'status' => 'published']);
        $field = FieldItem::query()->create(['field_group_id' => $group->id, 'title' => 'Public note', 'slug' => 'public_note', 'type' => 'text', 'options' => '{}']);
        $this->actingAs($member, 'member');
        $this->get(route('public.member.posts.create'), $this->headers())->assertOk()->assertJsonPath('props.customFieldGroups.0.items.0.slug', 'public_note');
        $this->postJson(route('public.member.posts.store'), ['name' => 'Custom field post', 'tag' => '[]', 'custom_fields' => json_encode([['id' => $group->id, 'items' => [['id' => $field->id, 'value' => 'Retained detail']]]])])->assertOk()->assertJsonPath('error', false);
        $post = Post::query()->where('name', 'Custom field post')->sole();
        $this->get(route('public.member.posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.customFieldGroups.0.items.0.value', 'Retained detail');
    }

    public function test_bulk_delete_checks_every_post_before_deleting_anything(): void
    {
        $member = $this->member();
        $other = $this->member('other@example.test');
        $own = Post::query()->create(['name' => 'Own', 'author_id' => $member->id, 'author_type' => Member::class]);
        $foreign = Post::query()->create(['name' => 'Foreign', 'author_id' => $other->id, 'author_type' => Member::class]);
        $this->actingAs($member, 'member');
        $this->postJson(route('public.member.posts.bulk-delete'), ['ids' => [$own->id, $foreign->id]])->assertNotFound();
        $this->assertDatabaseHas('posts', ['id' => $own->id]);
        $this->postJson(route('public.member.posts.bulk-delete'), ['ids' => [$own->id]])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseMissing('posts', ['id' => $own->id]);
        $this->assertDatabaseHas('posts', ['id' => $foreign->id]);
    }

    public function test_settings_keep_email_and_ownership_immutable_and_require_the_current_password(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'member');
        $this->postJson(route('public.member.post.settings'), ['first_name' => 'Updated', 'last_name' => 'Name', 'email' => 'attacker@example.test', 'avatar_id' => 999, 'gender' => 'other', 'description' => 'Retained profile'])->assertOk();
        $this->assertSame('member@example.test', $member->fresh()->email);
        $this->assertSame('Retained profile', $member->fresh()->description);
        $this->assertNotEquals(999, $member->fresh()->avatar_id);
        $this->putJson(route('public.member.post.security'), ['old_password' => 'wrong-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertUnprocessable()->assertJsonValidationErrors('old_password');
        $this->putJson(route('public.member.post.security'), ['old_password' => 'member-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
        $this->assertTrue(Hash::check('new-password', $member->fresh()->password));
    }

    public function test_react_editor_upload_uses_the_member_folder_and_rejects_invalid_files(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'member');
        $this->postJson(route('public.member.picker-upload'), ['file' => UploadedFile::fake()->create('invalid.txt')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $uploaded = (new MediaFile)->forceFill(['id' => 123, 'name' => 'Image', 'url' => 'members/'.$member->id.'/image.png', 'mime_type' => 'image/png']);
        RvMedia::partialMock()->shouldReceive('handleUpload')->once()->withArgs(fn ($file, $folder, $slug): bool => $file instanceof UploadedFile && $folder === 0 && $slug === $member->upload_folder)->andReturn(['error' => false, 'data' => $uploaded]);
        $this->postJson(route('public.member.picker-upload'), ['file' => UploadedFile::fake()->image('image.png')])->assertOk()->assertJsonPath('data.url', $uploaded->url)->assertJsonPath('data.id', '123');
    }

    public function test_activity_history_only_contains_the_current_members_records(): void
    {
        $member = $this->member();
        $other = $this->member('other@example.test');
        MemberActivityLog::query()->create(['member_id' => $member->id, 'action' => 'login']);
        MemberActivityLog::query()->create(['member_id' => $other->id, 'action' => 'logout']);
        $this->actingAs($member, 'member');
        $this->getJson(route('public.member.activity-logs'))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_scoped_bulk_changes_preserve_author_and_cannot_publish_posts(): void
    {
        $member = $this->member();
        $other = $this->member('other@example.test');
        $own = Post::query()->create(['name' => 'Own', 'author_id' => $member->id, 'author_type' => Member::class, 'status' => 'draft']);
        $foreign = Post::query()->create(['name' => 'Foreign', 'author_id' => $other->id, 'author_type' => Member::class]);
        $this->actingAs($member, 'member');
        $url = route('public.member.posts.bulk-update');
        $this->postJson($url, ['ids' => [$own->id], 'field' => 'status', 'value' => 'published'])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->postJson($url, ['ids' => [$own->id, $foreign->id], 'field' => 'name', 'value' => 'Attacked'])->assertNotFound();
        $this->assertSame('Own', $own->fresh()->name);
        $this->postJson($url, ['ids' => [$own->id], 'field' => 'status', 'value' => 'pending'])->assertOk();
        $this->assertSame('pending', $own->fresh()->status->getValue());
        $this->postJson($url, ['ids' => [$own->id], 'field' => 'created_at', 'value' => '2026-01-02'])->assertOk();
        $this->assertSame('2026-01-02', $own->fresh()->created_at->toDateString());
        $this->assertFalse(Route::has('public.member.table.bulk-action.dispatch'));
    }

    public function test_csv_exports_all_filtered_member_posts_and_escapes_formula_cells(): void
    {
        $member = $this->member();
        $other = $this->member('other@example.test');
        foreach (['=DANGEROUS()', 'Filtered first', 'Filtered second'] as $name) {
            Post::query()->create(['name' => $name, 'author_id' => $member->id, 'author_type' => Member::class]);
        }
        Post::query()->create(['name' => 'Foreign export', 'author_id' => $other->id, 'author_type' => Member::class]);
        $this->actingAs($member, 'member');
        $response = $this->get(route('public.member.posts.index', ['export' => 'csv', 'per_page' => 1]))->assertOk()->assertDownload('my-posts.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=DANGEROUS()", $csv);
        $this->assertStringContainsString('Filtered first', $csv);
        $this->assertStringContainsString('Filtered second', $csv);
        $this->assertStringNotContainsString('Foreign export', $csv);
        $filtered = $this->get(route('public.member.posts.index', ['export' => 'csv', 'search' => 'Filtered']))->streamedContent();
        $this->assertStringNotContainsString('DANGEROUS', $filtered);
    }

    public function test_member_asset_version_only_changes_when_its_own_asset_graph_changes(): void
    {
        $original = public_path();
        $temporary = sys_get_temp_dir().'/kern-member-assets-'.uniqid();
        File::ensureDirectoryExists($temporary.'/build/admin');
        $manifest = [
            'resources/js/admin.tsx' => ['file' => 'admin-one.js'],
            'resources/js/member.tsx' => ['file' => 'member-one.js', 'imports' => ['_shared.js']],
            '_shared.js' => ['file' => 'shared-one.js'],
        ];
        try {
            $this->app->usePublicPath($temporary);
            $path = $temporary.'/build/admin/manifest.json';
            file_put_contents($path, json_encode($manifest));
            $middleware = new HandleMemberInertiaRequests;
            $request = Request::create('/account/dashboard');
            $version = $middleware->version($request);
            $manifest['resources/js/admin.tsx']['file'] = 'admin-two.js';
            file_put_contents($path, json_encode($manifest));
            $this->assertSame($version, $middleware->version($request));
            $manifest['_shared.js']['file'] = 'shared-two.js';
            file_put_contents($path, json_encode($manifest));
            $this->assertNotSame($version, $middleware->version($request));
        } finally {
            $this->app->usePublicPath($original);
            File::deleteDirectory($temporary);
        }
    }

    public function test_member_form_serializer_rejects_foreign_models_even_outside_the_controller(): void
    {
        $member = $this->member();
        $other = $this->member('other@example.test');
        $post = Post::query()->create(['name' => 'Foreign', 'author_id' => $other->id, 'author_type' => Member::class]);
        $this->actingAs($member, 'member');
        $this->expectException(HttpException::class);
        app(MemberPostData::class)->formData('posts', $post);
    }

    public function test_existing_media_paths_are_not_rewritten_when_serializing_the_member_form(): void
    {
        $member = $this->member();
        $path = 'members/'.$member->id.'/existing-image.png';
        $post = Post::query()->create(['name' => 'Existing image', 'image' => $path, 'author_id' => $member->id, 'author_type' => Member::class]);
        $this->actingAs($member, 'member');
        $this->get(route('public.member.posts.edit', $post), $this->headers())->assertOk()
            ->assertJsonPath('props.values.image', $path)
            ->assertJsonPath('props.memberMediaUrlPattern', RvMedia::url('__KERN_MEDIA_PATH__'));
    }
}
