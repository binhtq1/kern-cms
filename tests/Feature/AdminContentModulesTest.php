<?php

namespace Tests\Feature;

use Database\Factories\ContactFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Kern\ACL\Models\User;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Facades\MetaBox;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\AdminRenderer;
use Kern\Block\Models\Block;
use Kern\Blog\Forms\PostForm;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Contact\Models\CustomField;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Gallery\Models\Gallery;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Member\Models\Member;
use Kern\PluginManagement\Services\PluginService;
use Kern\Setting\Facades\Setting;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminContentModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
        foreach (['blog', 'block', 'gallery', 'member', 'contact'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        foreach (array_keys(app(AdminContentResource::class)->modules()) as $module) {
            config(['core.base.admin.modules.'.$module => 'inertia']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_unsupported_custom_field_audit_refreshes_for_new_and_mutating_requests(): void
    {
        if (! is_plugin_active('custom-field')) {
            $this->assertFalse(app(PluginService::class)->activate('custom-field')['error']);
        }
        $this->get(route('posts.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Posts/Index');
        $group = FieldGroup::query()->create(['title' => 'Audit scope', 'status' => 'published', 'rules' => '[]']);
        $create = fn () => FieldItem::query()->create(['field_group_id' => $group->id, 'title' => 'Unsupported field', 'slug' => 'unsupported', 'type' => 'unsupported-acceptance', 'options' => '{}', 'order' => 1]);
        $field = $create();
        $this->get(route('posts.index'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $field->delete();
        $this->get(route('posts.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Posts/Index');
        Route::post('/admin/acceptance-mutate-field-audit', function () use ($create) {
            $resource = app(AdminContentResource::class);
            $before = $resource->fallbackReason('posts');
            $create();

            return response()->json(['before' => $before, 'after' => $resource->fallbackReason('posts')]);
        });
        $this->postJson('/admin/acceptance-mutate-field-audit')->assertOk()->assertJsonPath('before', null)->assertJsonPath('after', 'Blade is required by unsupported content custom fields.');
    }

    public function test_content_modules_render_structured_forms_and_ignore_legacy_module_flags(): void
    {
        foreach (app(AdminContentResource::class)->modules() as $module => $options) {
            $this->assertNull(app(AdminRenderer::class)->fallbackReason($module), $module);
            $this->get(route($options['route'].'.index'), $this->headers())->assertOk()->assertJsonPath('component', $options['screen'].'/Index')->assertJsonPath('props.permissions.edit', true);
            if ($module !== 'contacts') {
                $this->get(route($options['route'].'.create'), $this->headers())->assertOk()->assertJsonPath('component', $options['screen'].'/Form')->assertJsonStructure(['props' => ['fields', 'values', 'urls']]);
            }
            config(['core.base.admin.modules.'.$module => 'blade']);
            $this->get(route($options['route'].'.index'), $this->headers())->assertOk()->assertJsonPath('component', $options['screen'].'/Index');
            config(['core.base.admin.modules.'.$module => 'inertia']);
        }
    }

    public function test_category_inline_form_reuses_schema_and_saves_validated_fields(): void
    {
        $form = $this->getJson(route('categories.create', ['embedded' => '1']))->assertOk();
        $form->assertJsonPath('form.resource', 'categories')->assertJsonPath('form.id', null)->assertJsonPath('form.values.parent_id', '0');
        $this->assertContains('icon', array_column($form->json('form.fields'), 'name'));
        $this->postJson(route('categories.store', ['embedded' => '1']), ['name' => '', 'parent_id' => 0])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('categories', 0);
        $created = $this->postJson(route('categories.store', ['embedded' => '1']), ['name' => 'Inline category', 'parent_id' => 0, 'status' => 'published', 'icon' => 'ti ti-folder', 'is_default' => 1, 'slug' => 'inline-category', 'is_slug_editable' => 1, 'seo_meta' => ['seo_title' => 'Inline SEO']])->assertOk()->assertJsonPath('error', false);
        $id = $created->json('data.form.id');
        $this->assertNotNull($id);
        $created->assertJsonPath('data.form.values.name', 'Inline category')->assertJsonPath('data.form.values.icon', 'ti ti-folder')->assertJsonPath('data.form.values.slug', 'inline-category')->assertJsonPath('data.form.values.seo_meta.seo_title', 'Inline SEO');
        $this->postJson(route('categories.update', ['category' => $id, 'embedded' => '1']), ['name' => 'Inline updated', 'parent_id' => 0, 'status' => 'published', 'is_default' => 1])->assertOk()->assertJsonPath('data.form.values.name', 'Inline updated');
        $this->getJson(route('categories.edit', ['category' => $id, 'embedded' => '1']))->assertOk()->assertJsonPath('form.id', $id)->assertJsonPath('form.values.name', 'Inline updated');
        $this->get(route('categories.edit', ['category' => $id, 'embedded' => '1']), $this->headers())->assertOk()->assertJsonPath('component', 'Categories/Form');
        $this->get(route('categories.index'), $this->headers())->assertOk()->assertJsonPath('props.resource', 'categories')->assertJsonPath('props.items.data.0.name', 'Inline updated');
        $parent = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Inline parent', 'status' => 'published', 'parent_id' => 0]);
        $values = $created->json('data.form.values');
        $values['parent_id'] = (string) $parent->getKey();
        $values['is_default'] = 1;
        $first = $this->postJson(route('categories.update', ['category' => $id, 'embedded' => '1']), $values)->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $id, 'parent_id' => $parent->getKey()]);
        $this->assertSame((string) $parent->getKey(), (string) $first->json('data.form.values.parent_id'));
        $values = $first->json('data.form.values');
        $values['is_default'] = 0;
        $second = $this->postJson(route('categories.update', ['category' => $id, 'embedded' => '1']), $values)->assertOk()->assertJsonPath('data.form.values.icon', 'ti ti-folder')->assertJsonPath('data.form.values.seo_meta.seo_title', 'Inline SEO');
        $this->assertSame((string) $parent->getKey(), (string) $second->json('data.form.values.parent_id'));
        $this->assertDatabaseHas('categories', ['id' => $id, 'parent_id' => $parent->getKey(), 'is_default' => 0, 'icon' => 'ti ti-folder']);

    }

    public function test_category_inline_form_does_not_bypass_route_permissions_or_global_legacy_flag(): void
    {
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Private edit', 'status' => 'published', 'parent_id' => 0]);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['categories.index' => true]]));
        $this->getJson(route('categories.create', ['embedded' => '1']))->assertUnauthorized();
        $this->getJson(route('categories.edit', ['category' => $category, 'embedded' => '1']))->assertUnauthorized();
        $this->postJson(route('categories.update', ['category' => $category, 'embedded' => '1']), ['name' => 'Denied', 'parent_id' => 0, 'status' => 'published'])->assertUnauthorized();
        $this->assertSame('Private edit', $category->fresh()->name);
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.admin.legacy_mode' => true]);
        $this->getJson(route('categories.create', ['embedded' => '1']))->assertOk()->assertJsonPath('form.resource', 'categories');
    }

    public function test_new_blog_forms_preserve_native_select_defaults(): void
    {
        $post = $this->get(route('posts.create'), $this->headers())->assertOk();
        $post->assertJsonPath('props.values.status', 'published');
        $postStatus = collect($post->json('props.fields'))->firstWhere('name', 'status');
        $this->assertContains('published', array_column($postStatus['choices'], 'value'));

        $category = $this->get(route('categories.create'), $this->headers())->assertOk();
        $category->assertJsonPath('props.values.status', 'published')->assertJsonPath('props.values.parent_id', '0');
        $parent = collect($category->json('props.fields'))->firstWhere('name', 'parent_id');
        $this->assertSame(['value' => '0', 'label' => trans('plugins/blog::categories.none')], $parent['choices'][0]);

        $draft = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Draft story', 'status' => 'draft']);
        $this->get(route('posts.edit', $draft), $this->headers())->assertOk()->assertJsonPath('props.values.status', 'draft');

        $parentCategory = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Editorial parent', 'status' => 'published', 'parent_id' => 0]);
        $child = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Draft child', 'status' => 'draft', 'parent_id' => $parentCategory->id]);
        $edit = $this->get(route('categories.edit', $child), $this->headers())->assertOk()->assertJsonPath('props.values.status', 'draft');
        $this->assertSame((string) $parentCategory->id, (string) $edit->json('props.values.parent_id'));
        $editParent = collect($edit->json('props.fields'))->firstWhere('name', 'parent_id');
        $this->assertContains((string) $parentCategory->id, array_column($editParent['choices'], 'value'));
    }

    public function test_new_posts_only_select_published_default_categories_like_blade(): void
    {
        $published = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Published default', 'status' => 'published', 'parent_id' => 0, 'is_default' => true]);
        Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Draft default', 'status' => 'draft', 'parent_id' => 0, 'is_default' => true]);

        $this->get(route('posts.create'), $this->headers())->assertOk()->assertJsonPath('props.values.categories', [(string) $published->getKey()]);
    }

    public function test_post_category_choices_preserve_parent_ids_for_the_tree_picker(): void
    {
        $parent = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Editorial', 'status' => 'published', 'parent_id' => 0, 'order' => 0]);
        $child = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'News', 'status' => 'published', 'parent_id' => $parent->getKey(), 'order' => 0]);
        $grandchild = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Local', 'status' => 'published', 'parent_id' => $child->getKey(), 'order' => 0]);

        $response = $this->get(route('posts.create'), $this->headers())->assertOk();
        $categories = collect($response->json('props.fields'))->firstWhere('name', 'categories');

        $this->assertSame([
            ['value' => (string) $parent->getKey(), 'label' => 'Editorial', 'parent_id' => null],
            ['value' => (string) $child->getKey(), 'label' => 'News', 'parent_id' => (string) $parent->getKey()],
            ['value' => (string) $grandchild->getKey(), 'label' => 'Local', 'parent_id' => (string) $child->getKey()],
        ], $categories['choices']);
    }

    public function test_post_permalink_restores_custom_prefix_suffix_draft_preview_and_slug_generation(): void
    {
        Setting::set(SlugHelper::getPermalinkSettingKey(Post::class), 'stories')->save();
        config(['packages.theme.general.public_single_ending_url' => 'html']);
        $post = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Preview story', 'status' => 'draft']);

        $this->get(route('posts.edit', $post), $this->headers())->assertOk()
            ->assertJsonPath('props.permalink.pattern', url('stories').'/--slug--')
            ->assertJsonPath('props.permalink.ending', '.html')
            ->assertJsonPath('props.permalink.preview', true)
            ->assertJsonPath('props.permalink.generateUrl', route('slug.create'))
            ->assertJsonPath('props.permalink.model', Post::class);
        $this->postJson(route('slug.create'), ['value' => 'Preview story', 'slug_id' => '0', 'model' => Post::class])->assertOk()->assertContent('preview-story');
        $post->update(['status' => 'published']);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.permalink.preview', false);
    }

    public function test_stored_text_that_matches_a_php_function_is_never_invoked(): void
    {
        foreach (['time', 'trim'] as $text) {
            $block = Block::query()->create(['name' => 'Safe block '.$text, 'status' => 'published', 'raw_content' => $text]);
            $this->get(route('block.edit', $block), $this->headers())->assertOk()->assertJsonPath('props.values.raw_content', $text);
            $post = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Safe post '.$text, 'status' => 'draft', 'content' => $text]);
            $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.content', $text);
        }
    }

    public function test_categories_index_matches_the_standard_content_resource_list_and_reorders_without_renaming_or_losing_default(): void
    {
        $root = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Editorial', 'status' => 'published', 'parent_id' => 0, 'order' => 0, 'is_default' => true]);
        $child = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'News', 'status' => 'published', 'parent_id' => $root->id, 'order' => 0]);
        $other = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Reviews', 'status' => 'published', 'parent_id' => 0, 'order' => 1]);

        $response = $this->get(route('categories.index', ['per_page' => 1]), $this->headers())->assertOk()
            ->assertJsonPath('component', 'Categories/Index')
            ->assertJsonPath('props.resource', 'categories')
            ->assertJsonPath('props.items.total', 3)
            ->assertJsonCount(1, 'props.items.data');
        $this->assertSame(['id', 'name', 'created_at', 'status'], array_column($response->json('props.columns'), 'key'));
        $this->putJson(route('categories.update-tree'), ['data' => [
            ['id' => (string) $other->id, 'name' => 'Injected rename', 'children' => [['id' => (string) $root->id, 'name' => 'Injected', 'children' => [['id' => (string) $child->id, 'name' => 'Injected']]]]],
        ]])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('categories', ['id' => $root->id, 'name' => 'Editorial', 'parent_id' => $other->id, 'is_default' => true]);
        $this->assertDatabaseHas('categories', ['id' => $child->id, 'name' => 'News', 'parent_id' => $root->id]);
        $this->assertDatabaseHas('categories', ['id' => $other->id, 'name' => 'Reviews', 'parent_id' => 0, 'order' => 0]);
    }

    public function test_categories_reorder_requires_edit_permission_even_with_index_access(): void
    {
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Keep hierarchy', 'status' => 'published', 'parent_id' => 0, 'order' => 7]);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['categories.index' => true]]));

        $this->get(route('categories.index'), $this->headers())->assertOk()->assertJsonPath('props.permissions.edit', false);
        $this->putJson(route('categories.update-tree'), ['data' => [['id' => (string) $category->id, 'name' => $category->name]]])->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'parent_id' => 0, 'order' => 7]);
    }

    public function test_categories_reorder_rejects_invalid_complete_trees_before_any_write(): void
    {
        $root = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Root', 'status' => 'published', 'parent_id' => 0, 'order' => 7]);
        $child = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Child', 'status' => 'published', 'parent_id' => $root->id, 'order' => 3]);
        $node = fn (Category $category): array => ['id' => (string) $category->id, 'name' => $category->name];
        foreach ([
            [$node($root)],
            [$node($root), ['id' => '999999', 'name' => 'Unknown']],
            [[...$node($root), 'children' => [$node($root)]], $node($child)],
            [[...$node($root), 'children' => ['not-a-node']], $node($child)],
        ] as $tree) {
            $this->putJson(route('categories.update-tree'), ['data' => $tree])->assertUnprocessable()->assertJsonValidationErrors('data');
            $this->assertDatabaseHas('categories', ['id' => $root->id, 'parent_id' => 0, 'order' => 7]);
            $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => $root->id, 'order' => 3]);
        }
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_categories_reorder_rejects_depth_beyond_legacy_five_levels(): void
    {
        $categories = collect(range(1, 6))->map(fn ($number) => Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Level '.$number, 'status' => 'published', 'parent_id' => 0, 'order' => 7]));
        $tree = [];
        foreach ($categories->reverse() as $category) {
            $tree = [['id' => (string) $category->id, 'name' => $category->name, 'children' => $tree]];
        }
        $this->putJson(route('categories.update-tree'), ['data' => $tree])->assertUnprocessable()->assertJsonValidationErrors('data');
        foreach ($categories as $category) {
            $this->assertDatabaseHas('categories', ['id' => $category->id, 'parent_id' => 0, 'order' => 7]);
        }
    }

    public function test_categories_index_keeps_deep_or_detached_categories_accessible(): void
    {
        $parent = 0;
        foreach (range(1, 6) as $level) {
            $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Existing level '.$level, 'status' => 'published', 'parent_id' => $parent]);
            $parent = $category->id;
        }
        $this->get(route('categories.index'), $this->headers())->assertOk()->assertJsonPath('props.items.total', 6);
        Category::query()->whereKey($parent)->update(['parent_id' => 999999]);
        $this->get(route('categories.index'), $this->headers())->assertOk()->assertJsonPath('props.items.total', 6);
    }

    public function test_categories_translation_can_edit_names_but_cannot_reorder_shared_hierarchy(): void
    {
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                app(PluginService::class)->activate($plugin);
            }
        }
        LanguageAdvancedManager::registerModule(Category::class, ['name', 'description']);
        LanguageAdvancedManager::initModelRelations();
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        \Kern\Language\Facades\Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Default category', 'status' => 'published', 'parent_id' => 0, 'order' => 7]);
        DB::table('categories_translations')->insert(['categories_id' => $category->id, 'lang_code' => 'vi', 'name' => 'Danh mục']);

        $this->assertCount(2, \Kern\Language\Facades\Language::getActiveLanguage());
        $this->get(route('categories.index', ['ref_lang' => 'vi']), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.name', 'Danh mục')->assertJsonPath('props.items.data.0.edit_url', route('categories.edit', ['category' => $category->id, 'ref_lang' => 'vi']));
        $this->putJson(route('categories.update-tree', ['ref_lang' => 'vi']), ['data' => [['id' => (string) $category->id, 'name' => 'Danh mục']]])->assertUnprocessable()->assertJsonValidationErrors('data');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Default category', 'parent_id' => 0, 'order' => 7]);
        $embedded = $this->getJson(route('categories.edit', ['category' => $category, 'ref_lang' => 'vi', 'embedded' => '1']))->assertOk()->assertJsonPath('form.translatable', true)->assertJsonPath('form.values.name', 'Danh mục');
        $parentField = collect($embedded->json('form.fields'))->firstWhere('name', 'parent_id');
        $this->assertTrue($parentField['readonly']);
        $this->postJson(route('categories.update', ['category' => $category, 'ref_lang' => 'vi', 'embedded' => '1']), ['name' => 'Danh mục mới', 'description' => 'Mô tả', 'parent_id' => 0, 'status' => 'draft', 'is_default' => 1])->assertOk()->assertJsonPath('data.form.language', 'vi')->assertJsonPath('data.form.values.name', 'Danh mục mới');
        $this->assertDatabaseHas('categories_translations', ['categories_id' => $category->id, 'lang_code' => 'vi', 'name' => 'Danh mục mới']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Default category', 'status' => 'published', 'is_default' => 0, 'parent_id' => 0, 'order' => 7]);
        $this->get(route('categories.index', ['ref_lang' => 'en_US']), $this->headers())->assertOk()->assertJsonPath('props.resource', 'categories');
    }

    public function test_posts_preserve_relations_metadata_html_and_filter_pagination(): void
    {
        $this->post(route('categories.store'), ['name' => 'Editorial', 'status' => 'published', 'parent_id' => 0, 'is_default' => 1, 'submitter' => 'save'], $this->headers())->assertRedirect();
        $category = Category::query()->firstOrFail();
        $this->post(route('posts.store'), ['name' => 'Alpha Kern', 'status' => 'published', 'content' => '<section data-custom="keep"><h2>Original</h2></section>', 'categories' => [$category->getKey()], 'tag' => json_encode([['value' => 'CMS']]), 'slug' => 'alpha-kern', 'is_slug_editable' => 1, 'seo_meta' => ['seo_title' => 'Search title'], 'author_type' => 'Injected', 'submitter' => 'save'], $this->headers())->assertRedirect(route('posts.index'));
        $post = Post::query()->firstOrFail();
        $this->assertSame('<section data-custom="keep"><h2>Original</h2></section>', $post->content);
        $this->assertSame(auth()->id(), $post->author_id);
        $this->assertSame([$category->getKey()], $post->categories()->pluck('categories.id')->all());
        $this->assertSame(['CMS'], $post->tags()->pluck('name')->all());
        $this->assertSame('Search title', MetaBox::getMetaData($post, 'seo_meta', true)['seo_title']);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.tag', 'CMS')->assertJsonPath('props.values.slug', 'alpha-kern');
        $this->post(route('posts.store'), ['name' => 'Beta Kern', 'status' => 'draft', 'submitter' => 'save'], $this->headers())->assertRedirect();
        $this->get(route('posts.index', ['search' => 'kErN', 'sort' => 'name', 'direction' => 'desc', 'per_page' => 1, 'page' => 2]), $this->headers())->assertOk()->assertJsonPath('props.items.total', 2)->assertJsonPath('props.items.data.0.name', 'Alpha Kern');
        $this->get(route('posts.index', ['status' => 'draft']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1);
        $this->post(route('posts.update', $post), ['name' => 'Updated', 'status' => 'draft', 'categories' => [], 'tag' => '[]', 'submitter' => 'save'], $this->headers())->assertRedirect();
        $this->assertCount(0, $post->fresh()->categories);
        $this->assertCount(0, $post->fresh()->tags);
        $this->delete(route('posts.destroy', $post), [], $this->headers())->assertRedirect(route('posts.index'));
        $this->assertDatabaseMissing('posts', ['id' => $post->getKey()]);
    }

    public function test_block_list_exposes_copyable_shortcode_matching_blade_for_unicode_and_quoted_aliases(): void
    {
        $alias = 'café-đề-mục"quoted';
        $block = Block::query()->create(['name' => 'Shared block', 'alias' => $alias, 'status' => 'published', 'raw_content' => '<p>Content</p>']);
        DB::table($block->getTable())->where('id', $block->getKey())->update(['alias' => $alias]);
        $response = $this->get(route('block.index'), $this->headers())->assertOk()
            ->assertJsonPath('props.items.data.0.alias', $alias)
            ->assertJsonPath('props.items.data.0.shortcode', shortcode()->generateShortcode('static-block', ['alias' => $alias]));
        $columns = $response->json('props.columns');
        $this->assertSame(['id', 'name', 'alias', 'created_at', 'status'], array_column($columns, 'key'));
        $shortcode = collect($columns)->firstWhere('key', 'alias');
        $this->assertSame(trans('core/base::tables.shortcode'), $shortcode['label']);
        $this->assertFalse($shortcode['sortable']);
        $this->getJson(route('block.index', ['sort' => 'alias']))->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->assertSame($alias, $block->fresh()->alias);
    }

    public function test_blocks_and_galleries_save_source_and_ordered_image_metadata(): void
    {
        $this->post(route('block.store'), ['name' => 'Footer', 'alias' => 'footer', 'description' => 'Shared footer', 'content' => '[custom][/custom]', 'raw_content' => '<div data-component="footer">Footer</div>', 'status' => 'published', 'submitter' => 'save'], $this->headers())->assertRedirect();
        $block = Block::query()->firstOrFail();
        $this->assertSame('<div data-component="footer">Footer</div>', $block->raw_content);
        $this->get(route('block.edit', $block), $this->headers())->assertOk()->assertJsonPath('props.values.raw_content', $block->raw_content);
        $images = [['img' => 'https://example.test/second.jpg', 'description' => 'Second first'], ['img' => 'https://example.test/first.jpg', 'description' => 'First second']];
        $this->post(route('galleries.store'), ['name' => 'Collection', 'description' => '<p>Gallery</p>', 'order' => 2, 'status' => 'published', 'gallery' => json_encode($images), 'submitter' => 'save'], $this->headers())->assertRedirect();
        $gallery = Gallery::query()->firstOrFail();
        $this->assertSame($images, GalleryMeta::query()->where('reference_type', Gallery::class)->where('reference_id', $gallery->getKey())->firstOrFail()->images);
        $this->get(route('galleries.edit', $gallery), $this->headers())->assertOk()->assertJsonPath('props.gallery.0.description', 'Second first');
        $this->post(route('galleries.update', $gallery), ['name' => 'Collection', 'description' => 'Updated', 'order' => 3, 'status' => 'draft', 'gallery' => json_encode([['img' => 'javascript:alert(1)']])], $this->headers())->assertSessionHasErrors('gallery.0.img');
        $this->assertSame(2, (int) $gallery->fresh()->order);
    }

    public function test_admin_member_password_changes_are_explicit_and_secrets_never_shared(): void
    {
        $input = ['first_name' => 'Kern', 'last_name' => 'Member', 'email' => 'member@example.test', 'password' => 'initial-password', 'password_confirmation' => 'initial-password', 'status' => 'published', 'dob' => '2000-01-02', 'submitter' => 'save'];
        $this->post(route('member.store'), $input, $this->headers())->assertRedirect();
        $member = Member::query()->firstOrFail();
        $original = $member->password;
        $this->assertTrue(Hash::check('initial-password', $original));
        $this->get(route('member.edit', $member), $this->headers())->assertOk()->assertJsonPath('props.values.password', '')->assertJsonPath('props.values.dob', '2000-01-02')->assertDontSee($original);
        $this->post(route('member.update', $member), [...$input, 'password' => 'ignored-password', 'password_confirmation' => 'ignored-password', 'is_change_password' => 0, 'confirmed_at' => null], $this->headers())->assertRedirect();
        $this->assertSame($original, $member->fresh()->password);
        $this->assertNotNull($member->fresh()->confirmed_at);
        $this->post(route('member.update', $member), [...$input, 'password' => 'changed-password', 'password_confirmation' => 'changed-password', 'is_change_password' => 1], $this->headers())->assertRedirect();
        $this->assertTrue(Hash::check('changed-password', $member->fresh()->password));
    }

    public function test_post_custom_field_groups_are_editable_and_preserved_after_saving(): void
    {
        if (! is_plugin_active('custom-field')) {
            $this->assertFalse(app(PluginService::class)->activate('custom-field')['error']);
        }
        $group = FieldGroup::query()->create(['title' => 'Post details', 'status' => 'published', 'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Post::class]]])]);
        $field = FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Subtitle', 'slug' => 'subtitle', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        $post = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'With details', 'status' => 'draft']);

        $response = $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.customFieldGroups.0.title', 'Post details');
        $groups = $response->json('props.customFieldGroups');
        $groups[0]['items'][0]['value'] = 'Edited subtitle';
        $this->post(route('posts.update', $post), ['name' => $post->name, 'status' => 'draft', 'custom_fields' => json_encode($groups), 'submitter' => 'apply'], $this->headers())->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('custom_fields', ['use_for_id' => $post->getKey(), 'field_item_id' => $field->getKey(), 'slug' => 'subtitle', 'value' => 'Edited subtitle']);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.customFieldGroups.0.items.0.value', 'Edited subtitle');
    }

    public function test_contact_field_options_preserve_reordered_values_and_show_nested_validation_errors(): void
    {
        $field = CustomField::query()->create(['name' => 'Department', 'type' => 'dropdown', 'required' => true, 'order' => 1]);
        $support = $field->options()->create(['label' => 'Support', 'value' => 'support', 'order' => 0]);
        $sales = $field->options()->create(['label' => 'Sales', 'value' => 'sales', 'order' => 1]);
        $payload = ['name' => $field->name, 'type' => 'dropdown', 'required' => 1, 'order' => 1, 'submitter' => 'apply', 'options' => [
            ['id' => (string) $sales->getKey(), 'label' => 'Sales', 'value' => 'sales', 'order' => 0],
            ['id' => (string) $support->getKey(), 'label' => 'Support', 'value' => 'support', 'order' => 1],
        ]];

        $this->post(route('contacts.custom-fields.update', $field), $payload, $this->headers())->assertRedirect();

        $this->get(route('contacts.custom-fields.edit', $field), $this->headers())->assertOk()->assertJsonPath('props.fieldOptions.0.id', (string) $sales->getKey())->assertJsonPath('props.fieldOptions.1.id', (string) $support->getKey());
        $this->assertDatabaseHas('contact_custom_field_options', ['id' => $sales->getKey(), 'value' => 'sales', 'order' => 0]);
        $payload['options'][0]['label'] = '';
        $this->post(route('contacts.custom-fields.update', $field), $payload, $this->headers())->assertSessionHasErrors('options.0.label');
        $this->assertDatabaseHas('contact_custom_field_options', ['id' => $sales->getKey(), 'label' => 'Sales', 'order' => 0]);
    }

    public function test_post_custom_field_display_rules_match_categories_and_format_like_blade(): void
    {
        if (! is_plugin_active('custom-field')) {
            $this->assertFalse(app(PluginService::class)->activate('custom-field')['error']);
        }
        $category = Category::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Editorial', 'status' => 'published', 'parent_id' => 0]);
        $post = Post::query()->create(['author_type' => User::class, 'author_id' => auth()->id(), 'name' => 'Editorial story', 'status' => 'draft', 'format_type' => 'video']);
        $post->categories()->attach($category);
        $categoryGroup = FieldGroup::query()->create(['title' => 'Editorial details', 'status' => 'published', 'rules' => json_encode([[['name' => 'kern_blog_models_post_post_with_related_category', 'type' => '==', 'value' => (string) $category->getKey()]]])]);
        $formatGroup = FieldGroup::query()->create(['title' => 'Video details', 'status' => 'published', 'rules' => json_encode([[['name' => 'kern_blog_models_post_post_format', 'type' => '==', 'value' => 'video']]])]);
        foreach ([$categoryGroup, $formatGroup] as $group) {
            FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Caption', 'slug' => 'caption', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        }

        $response = $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonCount(2, 'props.customFieldGroups');

        $this->assertEqualsCanonicalizing(['Editorial details', 'Video details'], array_column($response->json('props.customFieldGroups'), 'title'));
        $post->categories()->detach();
        $post->update(['format_type' => '']);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonCount(0, 'props.customFieldGroups');
    }

    public function test_contacts_reply_requires_edit_permission_and_fields_cannot_reuse_other_options(): void
    {
        $contact = ContactFactory::new()->create(['subject' => 'Question']);
        EmailHandler::swap(\Mockery::mock(\Kern\Base\Supports\EmailHandler::class)->makePartial());
        EmailHandler::shouldReceive('send')->once()->with('Thanks for writing', 'Re: Question', $contact->email, [], true);
        $this->get(route('contacts.edit', $contact), $this->headers())->assertOk()->assertJsonPath('component', 'Contacts/Form')->assertJsonPath('props.contact.subject', 'Question');
        $this->post(route('contacts.reply', $contact), ['message' => 'Thanks for writing'], $this->headers())->assertRedirect();
        $this->assertDatabaseHas('contact_replies', ['contact_id' => $contact->getKey(), 'message' => 'Thanks for writing']);
        $this->assertSame('read', $contact->fresh()->status->getValue());
        $payload = ['name' => 'Department', 'type' => 'dropdown', 'required' => 1, 'order' => 1, 'options' => [['label' => 'Support', 'value' => 'support', 'order' => 1]], 'submitter' => 'save'];
        $this->post(route('contacts.custom-fields.store'), $payload, $this->headers())->assertRedirect();
        $first = CustomField::query()->firstOrFail();
        $this->post(route('contacts.custom-fields.store'), [...$payload, 'name' => 'Another'], $this->headers())->assertRedirect();
        $second = CustomField::query()->whereKeyNot($first->getKey())->firstOrFail();
        $this->post(route('contacts.custom-fields.update', $second), [...$payload, 'options' => [['id' => (string) $first->options()->firstOrFail()->getKey(), 'label' => 'Injected', 'value' => 'support', 'order' => 1]]], $this->headers())->assertSessionHasErrors('options.0.id');
        $this->assertDatabaseMissing('contact_custom_field_options', ['label' => 'Injected']);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['contacts.index' => true]]));
        $this->post(route('contacts.reply', $contact), ['message' => 'Denied'], $this->headers())->assertForbidden();
        $this->get(route('posts.index'), $this->headers())->assertForbidden();
    }

    public function test_bulk_updates_preserve_metadata_and_enforce_edit_delete_permissions(): void
    {
        $this->post(route('posts.store'), ['name' => 'Bulk post', 'status' => 'published', 'seo_meta' => ['seo_title' => 'Original'], 'submitter' => 'save'], $this->headers())->assertRedirect();
        $post = Post::query()->firstOrFail();
        $this->post(route('posts.bulk'), ['ids' => [$post->getKey()], 'action' => 'status', 'status' => 'draft', 'seo_meta' => ['seo_title' => 'Injected'], 'author_id' => 999], $this->headers())->assertRedirect(route('posts.index'));
        $this->assertSame('draft', $post->fresh()->status->getValue());
        $this->assertSame('Original', MetaBox::getMetaData($post->fresh(), 'seo_meta', true)['seo_title']);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['posts.index' => true, 'posts.edit' => true]]));
        $this->post(route('posts.bulk'), ['ids' => [$post->getKey()], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->getKey()]);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->post(route('posts.bulk'), ['ids' => [$post->getKey()], 'action' => 'delete'], $this->headers())->assertRedirect(route('posts.index'));
        $this->assertDatabaseMissing('posts', ['id' => $post->getKey()]);
    }

    public function test_category_tree_rejects_cycles_without_changing_the_existing_hierarchy(): void
    {
        $this->post(route('categories.store'), ['name' => 'Parent', 'status' => 'published', 'parent_id' => 0], $this->headers())->assertRedirect();
        $parent = Category::query()->firstOrFail();
        $this->post(route('categories.store'), ['name' => 'Child', 'status' => 'published', 'parent_id' => $parent->getKey()], $this->headers())->assertRedirect();
        $child = Category::query()->where('name', 'Child')->firstOrFail();
        $this->post(route('categories.update', $parent), ['name' => 'Parent', 'status' => 'published', 'parent_id' => $child->getKey()], $this->headers())->assertSessionHasErrors('parent_id');
        $this->assertSame(0, (int) $parent->fresh()->parent_id);
        $this->post(route('categories.update', $child), ['name' => 'Child', 'status' => 'published', 'parent_id' => $child->getKey()], $this->headers())->assertSessionHasErrors('parent_id');
    }

    public function test_published_posts_created_with_react_render_in_the_public_theme(): void
    {
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->post(route('posts.store'), ['name' => 'Public React post', 'status' => 'published', 'content' => '<p>Public editorial content</p>', 'slug' => 'public-react-post', 'is_slug_editable' => 1, 'submitter' => 'save'], $this->headers())->assertRedirect();
        $post = Post::query()->firstOrFail();
        $this->get($post->url)->assertOk()->assertSee('Public editorial content')->assertDontSee('resources/js/admin.tsx');
    }

    public function test_unknown_content_form_extensions_are_blocked_before_writes_and_invalid_requests_do_not_save(): void
    {
        $this->post(route('posts.store'), ['name' => '', 'status' => 'invalid'], $this->headers())->assertSessionHasErrors(['name', 'status']);
        $this->assertDatabaseCount('posts', 0);
        PostForm::extend(fn (PostForm $form) => $form->add('legacy_widget', HtmlField::class));
        $this->get(route('posts.create'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->post(route('posts.store'), ['name' => 'Must reload'], $this->headers())->assertStatus(503);
        $this->assertDatabaseCount('posts', 0);
    }
}
