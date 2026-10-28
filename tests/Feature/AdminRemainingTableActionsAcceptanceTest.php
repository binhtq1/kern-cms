<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Models\Role;
use Kern\Base\Facades\MetaBox;
use Kern\Block\Models\Block;
use Kern\Blog\Models\Post;
use Kern\Blog\Models\Tag;
use Kern\CustomField\Models\CustomField;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Gallery\Models\Gallery;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Member\Models\Member;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingTableActionsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $originalStorage;

    private string $originalPublic;

    private string $originalLang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsesTestingDatabase();
        $this->originalStorage = storage_path();
        $this->originalPublic = public_path();
        $this->originalLang = lang_path();
        $this->fixtureRoot = sys_get_temp_dir().'/kern-content-acceptance-'.bin2hex(random_bytes(8));
        foreach (['storage/framework/views', 'storage/framework/cache', 'storage/logs', 'public/build', 'lang/en', 'lang/vi'] as $folder) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$folder);
        }
        File::copyDirectory($this->originalPublic.'/build/admin', $this->fixtureRoot.'/public/build/admin');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config(['packages.installer.installer.enabled' => false, 'view.compiled' => storage_path('framework/views')]);
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot)) {
            $this->app->useStoragePath($this->originalStorage);
            $this->app->usePublicPath($this->originalPublic);
            $this->app->useLangPath($this->originalLang);
            File::deleteDirectory($this->fixtureRoot);
        }
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function publicTheme(): void
    {
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_page_bulk_template_and_created_date_preserve_all_unrelated_metadata(): void
    {
        $this->publicTheme();
        $group = FieldGroup::query()->create(['title' => 'Template preservation', 'status' => 'published', 'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Page::class]]])]);
        $field = FieldItem::query()->create(['field_group_id' => $group->id, 'title' => 'Preserved field', 'slug' => 'preserved_field', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        $images = [['img' => 'https://example.test/preserved.jpg', 'description' => 'Preserved caption']];
        $seo = ['seo_title' => 'Preserved SEO title', 'seo_description' => 'Preserved SEO description', 'index' => 'noindex'];
        $this->post(route('pages.store'), ['name' => 'Template preservation page', 'status' => 'published', 'content' => '<p>Preserved content</p>', 'template' => 'default', 'slug' => 'template-preservation', 'is_slug_editable' => 1, 'seo_meta' => $seo, 'gallery' => json_encode($images), 'banner_image' => 'https://example.test/banner.jpg'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $page = Page::query()->where('name', 'Template preservation page')->sole();
        $groups = $this->get(route('pages.edit', $page), $this->headers())->assertOk()->json('props.customFieldGroups');
        $groups[0]['items'][0]['value'] = 'Preserved metadata value';
        $this->post(route('pages.update', $page), ['name' => $page->name, 'status' => 'published', 'custom_fields' => json_encode($groups)], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $metadata = $page->metadata()->orderBy('meta_key')->get(['meta_key', 'meta_value'])->toArray();
        $gallery = GalleryMeta::query()->where('reference_type', Page::class)->where('reference_id', $page->id)->sole()->images;
        $custom = CustomField::query()->where('use_for_id', $page->id)->where('field_item_id', $field->id)->sole()->getRawOriginal('value');
        $other = Page::query()->create(['name' => 'Unselected page', 'status' => 'published', 'template' => 'default']);
        $unchanged = $other->fresh()->getAttributes();
        foreach (['template' => 'no-sidebar', 'created_at' => '2020-02-03'] as $key => $value) {
            $this->post(route('pages.bulk'), ['ids' => [$page->id], 'action' => 'update', 'field' => $key, 'value' => $value], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('2020-02-03', $page->fresh()->created_at->toDateString());
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        $this->assertSame('no-sidebar', $page->fresh()->template);
        $this->assertSame('<p>Preserved content</p>', $page->fresh()->content);
        $this->assertSame($metadata, $page->fresh()->metadata()->orderBy('meta_key')->get(['meta_key', 'meta_value'])->toArray());
        $this->assertSame($gallery, GalleryMeta::query()->where('reference_type', Page::class)->where('reference_id', $page->id)->sole()->images);
        $this->assertSame($custom, CustomField::query()->where('use_for_id', $page->id)->where('field_item_id', $field->id)->sole()->getRawOriginal('value'));
        $this->get(route('pages.edit', $page), $this->headers())->assertOk()->assertJsonPath('props.page.template', 'no-sidebar')->assertJsonPath('props.page.seo_meta.seo_title', $seo['seo_title'])->assertJsonPath('props.page.banner_image', 'https://example.test/banner.jpg')->assertJsonPath('props.gallery.0.description', 'Preserved caption')->assertJsonPath('props.customFieldGroups.0.items.0.value', 'Preserved metadata value');
    }

    private function contentFixture(string $route, string $name): Model
    {
        $payload = match ($route) {
            'tags' => ['name' => $name, 'description' => 'Retained tag summary', 'status' => 'published'],
            'block' => ['name' => $name, 'alias' => str($name)->slug()->toString(), 'description' => 'Retained block summary', 'content' => '<p>Retained block body</p>', 'status' => 'published'],
            'galleries' => ['name' => $name, 'description' => 'Retained gallery summary', 'order' => 2, 'status' => 'published', 'gallery' => json_encode([['img' => 'https://example.test/retained.jpg', 'description' => 'Retained caption']])],
            'contacts.custom-fields' => ['name' => $name, 'type' => 'dropdown', 'order' => 2, 'required' => 0, 'status' => 'published', 'options' => [['label' => 'Support', 'value' => 'support', 'order' => 0]]],
        };
        $this->postJson(route($route.'.store'), $payload)->assertOk()->assertJsonPath('error', false);
        $class = match ($route) {
            'tags' => Tag::class,
            'block' => Block::class,
            'galleries' => Gallery::class,
            'contacts.custom-fields' => \Kern\Contact\Models\CustomField::class,
        };

        return $class::query()->where('name', $name)->sole();
    }

    #[TestWith(['tags', true])]
    #[TestWith(['block', true])]
    #[TestWith(['galleries', true])]
    #[TestWith(['contacts.custom-fields', false])]
    public function test_registered_content_bulk_changes_and_deletion_touch_only_selected_rows(string $route, bool $hasStatus): void
    {
        $selected = $this->contentFixture($route, 'Selected resource');
        $other = $this->contentFixture($route, 'Unselected resource');
        $unchanged = $other->fresh()->getAttributes();
        MetaBox::saveMetaBoxData($selected, 'acceptance_keep', ['marker' => 'Retained unrelated metadata']);
        $metadata = $selected->metadata()->orderBy('id')->get()->toArray();
        $gallery = GalleryMeta::query()->where('reference_type', $selected::class)->where('reference_id', $selected->id)->get()->toArray();
        $options = $route === 'contacts.custom-fields' ? $selected->options()->get()->toArray() : [];
        if ($route === 'contacts.custom-fields') {
            $this->actingAs(UserFactory::new()->create(['permissions' => ['contacts.index' => true]]));
            $this->post(route($route.'.bulk'), ['ids' => [$selected->id], 'action' => 'change', 'field' => 'name', 'value' => 'Denied name'], $this->headers())->assertForbidden();
            $this->assertSame('Selected resource', $selected->fresh()->name);
            $this->actingAs(UserFactory::new()->create(['permissions' => ['contact.custom-fields' => true]]));
        }
        $changes = ['name' => 'Bulk renamed resource', 'created_at' => '2020-04-05'];
        if ($hasStatus) {
            $changes['status'] = 'draft';
        }
        foreach ($changes as $key => $value) {
            $this->post(route($route.'.bulk'), ['ids' => [$selected->id], 'action' => 'change', 'field' => $key, 'value' => $value], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        }
        $fresh = $selected->fresh();
        $this->assertSame('Bulk renamed resource', $fresh->name);
        $this->assertSame('2020-04-05', $fresh->created_at->toDateString());
        if ($hasStatus) {
            $this->assertSame('draft', $fresh->status->getValue());
        }
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        $this->assertSame($metadata, $fresh->metadata()->orderBy('id')->get()->toArray());
        $this->assertSame($gallery, GalleryMeta::query()->where('reference_type', $selected::class)->where('reference_id', $selected->id)->get()->toArray());
        if ($route === 'contacts.custom-fields') {
            $this->assertSame($options, $fresh->options()->get()->toArray());
        }
        $this->get(route($route.'.edit', $selected), $this->headers())->assertOk()->assertJsonPath('props.values.name', 'Bulk renamed resource');
        $this->post(route($route.'.bulk'), ['ids' => [$selected->id], 'action' => 'delete'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($selected);
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        if ($route === 'contacts.custom-fields') {
            $this->assertSame(0, $selected->options()->count());
        }
    }

    public function test_menu_missing_registered_name_and_created_date_changes_preserve_nodes_and_other_menus(): void
    {
        $menu = MenuModel::query()->create(['name' => 'Selected navigation', 'status' => 'published']);
        $other = MenuModel::query()->create(['name' => 'Unselected navigation', 'status' => 'published']);
        $menu->menuNodes()->create(['title' => 'Retained node', 'url' => '/retained', 'position' => 0]);
        $nodes = $menu->menuNodes()->get()->toArray();
        $unchanged = $other->fresh()->getAttributes();
        foreach (['name' => 'Bulk renamed navigation', 'created_at' => '2020-06-07'] as $key => $value) {
            $this->post(route('menus.bulk'), ['ids' => [$menu->id], 'action' => 'change', 'field' => $key, 'value' => $value], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('Bulk renamed navigation', $menu->fresh()->name);
        $this->assertSame('2020-06-07', $menu->fresh()->created_at->toDateString());
        $this->assertSame($nodes, $menu->menuNodes()->get()->toArray());
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.name', 'Bulk renamed navigation')->assertJsonPath('props.menu.nodes.0.title', 'Retained node');
    }

    public function test_member_missing_registered_first_name_email_and_date_changes_and_scoped_deletion(): void
    {
        $member = Member::query()->create(['first_name' => 'Selected', 'last_name' => 'Member', 'email' => 'selected@example.test', 'password' => 'retained-password', 'confirmed_at' => now()]);
        $other = Member::query()->create(['first_name' => 'Unselected', 'last_name' => 'Member', 'email' => 'unselected@example.test', 'password' => 'other-password']);
        $password = $member->getRawOriginal('password');
        $confirmed = $member->getRawOriginal('confirmed_at');
        $unchanged = $other->fresh()->getAttributes();
        foreach (['first_name' => 'Bulk renamed', 'email' => 'bulk-renamed@example.test', 'created_at' => '2020-08-09'] as $key => $value) {
            $this->post(route('member.bulk'), ['ids' => [$member->id], 'action' => 'change', 'field' => $key, 'value' => $value], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('Bulk renamed', $member->fresh()->first_name);
        $this->assertSame('bulk-renamed@example.test', $member->fresh()->email);
        $this->assertSame('2020-08-09', $member->fresh()->created_at->toDateString());
        $this->assertSame($password, $member->fresh()->getRawOriginal('password'));
        $this->assertSame($confirmed, $member->fresh()->getRawOriginal('confirmed_at'));
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        $this->get(route('member.edit', $member), $this->headers())->assertOk()->assertJsonPath('props.values.email', 'bulk-renamed@example.test');
        $this->post(route('member.bulk'), ['ids' => [$member->id], 'action' => 'delete'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($member);
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
    }

    public function test_member_verify_email_requires_edit_permission_and_updates_only_unverified_target(): void
    {
        setting()->set('verify_account_email', 1)->save();
        $member = Member::query()->create(['first_name' => 'Pending', 'last_name' => 'Verification', 'email' => 'pending@example.test', 'password' => 'retained-password']);
        $other = Member::query()->create(['first_name' => 'Other', 'last_name' => 'Verification', 'email' => 'other@example.test', 'password' => 'other-password']);
        $unchanged = $other->fresh()->getAttributes();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['member.index' => true]]));
        $this->post(route('member.verify-email', $member->id), [], $this->headers())->assertForbidden();
        $this->assertNull($member->fresh()->confirmed_at);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['member.index' => true, 'member.edit' => true]]));
        $this->post(route('member.verify-email', $member->id), [], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($member->fresh()->confirmed_at);
        $this->assertSame($unchanged, $other->fresh()->getAttributes());
        $this->post(route('member.verify-email', $member->id), [], $this->headers())->assertNotFound();
    }

    public function test_page_bulk_redirect_preserves_applied_query_and_mutates_only_selected_ids(): void
    {
        $this->publicTheme();
        $pages = [];
        foreach (['Scoped A', 'Scoped B', 'Scoped C', 'Unrelated page'] as $name) {
            $pages[] = Page::query()->create(['name' => $name, 'template' => 'default', 'status' => 'published']);
        }
        $query = ['search' => 'Scoped', 'status' => 'published', 'template' => 'default', 'sort' => 'name', 'direction' => 'asc', 'page' => 2, 'per_page' => 1];
        $this->get(route('pages.index', $query), $this->headers())->assertOk()->assertJsonPath('props.pages.data.0.id', (string) $pages[1]->id);
        $response = $this->post(route('pages.bulk', $query), ['ids' => [$pages[1]->id], 'action' => 'update', 'field' => 'template', 'value' => 'no-sidebar'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(route('pages.index'), strtok($response->headers->get('Location'), '?'));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $returned);
        $this->assertEquals($query, $returned);
        $this->assertSame('no-sidebar', $pages[1]->fresh()->template);
        foreach ([0, 2, 3] as $index) {
            $this->assertSame('default', $pages[$index]->fresh()->template);
        }
        $this->get(route('pages.index', $query), $this->headers())->assertOk()->assertJsonPath('props.filters.search', 'Scoped')->assertJsonPath('props.filters.template', 'default')->assertJsonPath('props.filters.page', '2')->assertJsonPath('props.pages.data.0.id', (string) $pages[2]->id);
    }

    #[TestWith(['posts'])]
    #[TestWith(['menus'])]
    #[TestWith(['roles'])]
    public function test_registered_and_acl_bulk_redirects_preserve_applied_search_filters_and_pagination(string $module): void
    {
        $class = match ($module) {
            'posts' => Post::class,
            'menus' => MenuModel::class,
            'roles' => Role::class,
        };
        $attributes = $module === 'roles' ? ['permissions' => [], 'created_by' => auth()->id(), 'updated_by' => auth()->id()] : ['status' => 'published'];
        $selected = $class::query()->create(['name' => 'Scoped A', ...$attributes]);
        $other = $class::query()->create(['name' => 'Scoped B', ...$attributes]);
        $query = ['search' => 'Scoped', 'sort' => 'name', 'direction' => 'asc', 'page' => 1, 'per_page' => 1, 'advanced' => [['field' => 'name', 'operator' => 'like', 'value' => 'Scoped']]];
        if ($module !== 'roles') {
            $query['status'] = 'published';
        }
        $response = $this->from(route($module.'.index', $query))->post(route($module.'.bulk', $query), ['ids' => [$selected->id], 'action' => 'change', 'field' => 'name', 'value' => 'Scoped renamed'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(route($module.'.index'), strtok($response->headers->get('Location'), '?'));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $returned);
        $this->assertEquals($query, $returned);
        $this->assertSame('Scoped renamed', $selected->fresh()->name);
        $this->assertSame('Scoped B', $other->fresh()->name);
        $this->get(route($module.'.index', $query), $this->headers())->assertOk()->assertJsonPath('props.filters.search', 'Scoped')->assertJsonPath('props.filters.advanced.0.value', 'Scoped');
    }

    public function test_bulk_return_query_drops_untrusted_keys_and_rejects_invalid_pagination_before_writes(): void
    {
        $page = Page::query()->create(['name' => 'Scoped page', 'template' => 'default', 'status' => 'published']);
        $payload = ['ids' => [$page->id], 'action' => 'update', 'field' => 'name', 'value' => 'Scoped renamed page'];
        $query = ['search' => 'Scoped', 'return_url' => 'https://other.test/redirect', '_export' => 'csv', 'ids' => [999999], 'action' => 'delete'];
        $this->from('https://other.test/arbitrary')->post(route('pages.bulk', $query), $payload, $this->headers())->assertRedirect(route('pages.index', ['search' => 'Scoped']));
        $this->assertSame('Scoped renamed page', $page->fresh()->name);
        $this->post(route('pages.bulk', ['page' => -1]), [...$payload, 'value' => 'Must not be stored'], $this->headers())->assertSessionHasErrors('page');
        $this->assertSame('Scoped renamed page', $page->fresh()->name);
    }
}
