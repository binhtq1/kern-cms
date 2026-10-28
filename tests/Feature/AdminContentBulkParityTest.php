<?php

namespace Tests\Feature;

use Database\Factories\ContactFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\Base\Facades\MetaBox;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\CustomField\Models\FieldGroup;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Member\Models\Member;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminContentBulkParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        foreach (['blog', 'block', 'gallery', 'member', 'contact', 'custom-field'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        foreach (['posts', 'tags', 'blocks', 'galleries', 'members', 'contacts', 'contact-fields', 'field-groups', 'users', 'roles'] as $module) {
            config(['core.base.admin.modules.'.$module => 'inertia']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    public function test_read_choice_memoization_is_scoped_to_request_module_locale_and_actor(): void
    {
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'First category', 'status' => 'published']);
        $other = UserFactory::new()->create();
        Route::get('/admin/acceptance-read-choices', function (Request $request) use ($other) {
            $table = app(AdminRegisteredTableData::class);
            $first = get_blog_category_choices();
            $this->assertSame($first, get_blog_category_choices());
            $posts = $table->fields('posts');
            $this->assertSame($posts, $table->fields('posts'));
            $tags = $table->fields('tags');
            app()->setLocale('vi');
            $localized = get_blog_category_choices();
            $request->merge(['ref_lang' => 'vi']);
            $translated = get_blog_category_choices();
            $request->setUserResolver(fn () => $other);
            $otherActor = get_blog_category_choices();

            return response()->json(compact('first', 'posts', 'tags', 'localized', 'translated', 'otherActor'));
        });
        DB::enableQueryLog();
        try {
            $response = $this->getJson('/admin/acceptance-read-choices')->assertOk();
            $categoryQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "categories"'));
            $this->assertCount(5, $categoryQueries);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertContains('category', array_column($response->json('posts'), 'name'));
        $this->assertNotContains('category', array_column($response->json('tags'), 'name'));
        $category->update(['name' => 'Updated category']);
        $this->getJson('/admin/acceptance-read-choices')->assertOk()->assertJsonPath('first.'.$category->id, 'Updated category');
    }

    public function test_mutating_requests_do_not_reuse_category_choices_or_registered_bulk_options(): void
    {
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Before mutation', 'status' => 'published']);
        Route::post('/admin/acceptance-mutate-choices', function () use ($category) {
            $table = app(AdminRegisteredTableData::class);
            $before = get_blog_category_choices();
            $beforeBulk = collect($table->fields('posts'))->firstWhere('name', 'category')['choices'];
            $category->update(['name' => 'After mutation']);

            return response()->json(['before' => $before, 'beforeBulk' => $beforeBulk, 'after' => get_blog_category_choices(), 'afterBulk' => collect($table->fields('posts'))->firstWhere('name', 'category')['choices']]);
        });
        $this->postJson('/admin/acceptance-mutate-choices')->assertOk()->assertJsonPath('before.'.$category->id, 'Before mutation')->assertJsonPath('after.'.$category->id, 'After mutation')->assertJsonPath('beforeBulk.0.label', 'Before mutation')->assertJsonPath('afterBulk.0.label', 'After mutation');
    }

    public function test_registered_post_bulk_callbacks_and_advanced_filters_preserve_unrelated_metadata(): void
    {
        $this->post(route('posts.store'), ['name' => 'Alpha original', 'status' => 'published', 'seo_meta' => ['seo_title' => 'Keep title']], $this->headers())->assertRedirect();
        $post = Post::query()->firstOrFail();
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Editorial', 'status' => 'published']);
        $other = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Other post', 'status' => 'published']);
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'change', 'field' => 'category', 'value' => (string) $category->id, 'author_id' => 999], $this->headers())->assertRedirect(route('posts.index'));
        $this->assertSame([$category->id], $post->categories()->pluck('categories.id')->all());
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'change', 'field' => 'name', 'value' => 'Alpha renamed'], $this->headers())->assertRedirect();
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'change', 'field' => 'created_at', 'value' => '2020-03-04'], $this->headers())->assertRedirect();
        $this->assertSame('2020-03-04', $post->fresh()->created_at->toDateString());
        $this->assertSame('Keep title', MetaBox::getMetaData($post->fresh(), 'seo_meta', true)['seo_title']);
        $filters = [['field' => 'category', 'operator' => '=', 'value' => (string) $category->id], ['field' => 'name', 'operator' => 'like', 'value' => 'Alpha']];
        $this->get(route('posts.index', ['advanced' => $filters]), $this->headers())->assertOk()->assertJsonCount(1, 'props.items.data')->assertJsonPath('props.items.data.0.id', (string) $post->id)->assertJsonPath('props.filters.advanced', $filters);
        $this->assertSame('Other post', $other->fresh()->name);
    }

    public function test_unknown_fields_operators_choices_and_mixed_missing_ids_are_rejected_before_changes(): void
    {
        $post = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Unchanged', 'status' => 'published']);
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'change', 'field' => 'author_id', 'value' => '999'], $this->headers())->assertSessionHasErrors('field');
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'change', 'field' => 'category', 'value' => '999999'], $this->headers())->assertSessionHasErrors('value');
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'status', 'status' => 'invalid'], $this->headers())->assertSessionHasErrors('status');
        $this->post(route('posts.bulk'), ['ids' => [$post->id, 999999], 'action' => 'change', 'field' => 'name', 'value' => 'Changed'], $this->headers())->assertSessionHasErrors('ids.1');
        $this->get(route('posts.index', ['advanced' => [['field' => 'author_id', 'operator' => '=', 'value' => '1']]]), $this->headers())->assertSessionHasErrors('advanced.0');
        $this->get(route('posts.index', ['advanced' => [['field' => 'name', 'operator' => 'OR 1=1', 'value' => '1']]]), $this->headers())->assertSessionHasErrors('advanced.0');
        $this->assertSame('Unchanged', $post->fresh()->name);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['posts.index' => true]]));
        $this->post(route('posts.bulk'), ['ids' => [$post->id], 'action' => 'change', 'field' => 'name', 'value' => 'Denied'], $this->headers())->assertForbidden();
    }

    public function test_member_email_bulk_changes_reject_collisions_and_multi_account_updates(): void
    {
        $first = Member::query()->create(['first_name' => 'First', 'last_name' => 'Member', 'email' => 'first@example.test', 'password' => 'unused']);
        $second = Member::query()->create(['first_name' => 'Second', 'last_name' => 'Member', 'email' => 'second@example.test', 'password' => 'unused']);
        $this->post(route('member.bulk'), ['ids' => [$first->id, $second->id], 'action' => 'change', 'field' => 'email', 'value' => 'both@example.test'], $this->headers())->assertSessionHasErrors('value');
        $this->post(route('member.bulk'), ['ids' => [$first->id], 'action' => 'change', 'field' => 'email', 'value' => $second->email], $this->headers())->assertSessionHasErrors('value');
        $this->assertSame('first@example.test', $first->fresh()->email);
        $this->post(route('member.bulk'), ['ids' => [$first->id], 'action' => 'change', 'field' => 'last_name', 'value' => 'Updated'], $this->headers())->assertRedirect();
        $this->post(route('member.bulk'), ['ids' => [$first->id], 'action' => 'status', 'status' => 'draft'], $this->headers())->assertRedirect();
        $this->assertSame('Updated', $first->fresh()->last_name);
        $this->assertSame('draft', $first->fresh()->status);
    }

    public function test_field_groups_bulk_changes_filtering_and_delete_require_explicit_permissions(): void
    {
        $actor = auth()->user();
        $group = FieldGroup::query()->create(['title' => 'Original group', 'status' => 'published', 'order' => 1, 'rules' => '[]', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $otherGroup = FieldGroup::query()->create(['title' => 'ZZZ other group', 'status' => 'published', 'order' => 0, 'rules' => '[]', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->get(route('custom-fields.index', ['sort' => 'name', 'direction' => 'asc']), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.name', 'Original group');
        $this->get(route('custom-fields.index', ['sort' => 'name', 'direction' => 'desc']), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.name', 'ZZZ other group');
        $this->post(route('custom-fields.bulk'), ['ids' => [$group->id], 'action' => 'change', 'field' => 'title', 'value' => 'Updated group'], $this->headers())->assertRedirect(route('custom-fields.index'));
        $this->post(route('custom-fields.bulk'), ['ids' => [$group->id], 'action' => 'status', 'status' => 'draft'], $this->headers())->assertRedirect();
        $this->get(route('custom-fields.index', ['advanced' => [['field' => 'title', 'operator' => 'like', 'value' => 'Updated']]]), $this->headers())->assertOk()->assertJsonCount(1, 'props.items.data')->assertJsonPath('props.items.data.0.name', 'Updated group');
        $otherCreatedAt = $otherGroup->created_at->toDateTimeString();
        $this->post(route('custom-fields.bulk'), ['ids' => [$group->id], 'action' => 'change', 'field' => 'created_at', 'value' => '2020-03-04'], $this->headers())->assertRedirect();
        $this->assertSame('2020-03-04', $group->fresh()->created_at->toDateString());
        $this->assertSame($otherCreatedAt, $otherGroup->fresh()->created_at->toDateTimeString());
        $this->post(route('custom-fields.bulk'), ['ids' => [$group->id, $otherGroup->id], 'action' => 'change', 'field' => 'created_at', 'value' => 'invalid-date'], $this->headers())->assertSessionHasErrors('value');
        $this->assertSame('2020-03-04', $group->fresh()->created_at->toDateString());
        $this->assertSame($otherCreatedAt, $otherGroup->fresh()->created_at->toDateTimeString());
        $this->actingAs(UserFactory::new()->create(['permissions' => ['custom-fields.index' => true]]));
        $this->post(route('custom-fields.bulk'), ['ids' => [$group->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->assertDatabaseHas('field_groups', ['id' => $group->id, 'status' => 'draft']);
        $this->actingAs($actor);
        $this->post(route('custom-fields.bulk'), ['ids' => [$group->id], 'action' => 'delete'], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('field_groups', ['id' => $group->id]);
    }

    public function test_acl_registered_changes_allow_self_edits_but_cannot_bypass_activation_or_super_guards(): void
    {
        $actor = auth()->user();
        $other = UserFactory::new()->create();
        $this->post(route('users.bulk'), ['ids' => [$actor->id], 'action' => 'change', 'field' => 'username', 'value' => 'updated-self'], $this->headers())->assertRedirect();
        $this->assertSame('updated-self', $actor->fresh()->username);
        $this->post(route('users.bulk'), ['ids' => [$actor->id], 'action' => 'change', 'field' => 'status', 'value' => 'deactivated'], $this->headers())->assertSessionHasErrors('field');
        $this->post(route('users.bulk'), ['ids' => [$actor->id, $other->id], 'action' => 'change', 'field' => 'email', 'value' => 'same@example.test'], $this->headers())->assertSessionHasErrors('value');
        $this->post(route('users.bulk'), ['ids' => [$actor->id], 'action' => 'change', 'field' => 'email', 'value' => $other->email], $this->headers())->assertSessionHasErrors('value');
        $limited = UserFactory::new()->create(['permissions' => ['users.index' => true, 'users.edit' => true, 'roles.index' => true, 'roles.edit' => true]]);
        $role = Role::query()->create(['name' => 'Privileged', 'permissions' => [ACL_ROLE_SUPER_USER => true], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->actingAs($limited);
        $this->post(route('users.bulk'), ['ids' => [$other->id, $actor->id], 'action' => 'change', 'field' => 'created_at', 'value' => '2020-03-04'], $this->headers())->assertForbidden();
        $this->post(route('roles.bulk'), ['ids' => [$role->id], 'action' => 'change', 'field' => 'name', 'value' => 'Denied'], $this->headers())->assertForbidden();
        $this->assertSame('Privileged', $role->fresh()->name);
    }

    public function test_translated_registered_bulk_name_keeps_global_fields_and_other_translated_columns(): void
    {
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        LanguageAdvancedManager::registerModule(Post::class, ['name', 'description', 'content']);
        LanguageAdvancedManager::initModelRelations();
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        \Kern\Language\Facades\Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        $post = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Default post', 'description' => 'Default description', 'content' => 'Default content', 'status' => 'published']);
        DB::table('posts_translations')->insert(['posts_id' => $post->id, 'lang_code' => 'vi', 'name' => 'Tên cũ', 'description' => 'Giữ mô tả', 'content' => 'Giữ nội dung']);
        $this->post(route('posts.bulk', ['ref_lang' => 'vi']), ['ids' => [$post->id], 'action' => 'change', 'field' => 'name', 'value' => 'Tên mới'], $this->headers())->assertRedirect(route('posts.index', ['ref_lang' => 'vi']));
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'name' => 'Default post', 'status' => 'published']);
        $this->assertDatabaseHas('posts_translations', ['posts_id' => $post->id, 'lang_code' => 'vi', 'name' => 'Tên mới', 'description' => 'Giữ mô tả', 'content' => 'Giữ nội dung']);
        $this->post(route('posts.bulk', ['ref_lang' => 'vi']), ['ids' => [$post->id], 'action' => 'change', 'field' => 'created_at', 'value' => '2020-03-04'], $this->headers())->assertSessionHasErrors('field');
        $this->get(route('posts.index', ['ref_lang' => 'vi', 'advanced' => [['field' => 'name', 'operator' => 'like', 'value' => 'Tên mới']]]), $this->headers())->assertOk()->assertJsonCount(1, 'props.items.data')->assertJsonPath('props.items.data.0.name', 'Tên mới');
    }

    public function test_contact_export_applies_registered_advanced_filters_from_the_visible_list(): void
    {
        ContactFactory::new()->create(['name' => 'Matched sender', 'email' => 'matched@example.test', 'phone' => '555-001']);
        ContactFactory::new()->create(['name' => 'Other sender', 'email' => 'excluded@example.test', 'phone' => '222-001']);
        $response = $this->post(route('contacts.export'), ['format' => 'csv', 'advanced' => [['field' => 'phone', 'operator' => 'like', 'value' => '555']]])->assertOk();
        $text = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('matched@example.test', $text);
        $this->assertStringNotContainsString('excluded@example.test', $text);
    }
}
