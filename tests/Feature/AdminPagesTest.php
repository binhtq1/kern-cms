<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Models\Role;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Facades\MetaBox;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminExtensionRegistry;
use Kern\Base\Supports\AdminRenderer;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Language\Models\Language;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Kern\Page\Forms\PageForm;
use Kern\Page\Http\Controllers\PageController;
use Kern\Page\Models\Page;
use Kern\Page\Tables\PageTable;
use Kern\PluginManagement\Services\PluginService;
use Kern\Revision\Revision;
use Kern\Setting\Facades\Setting;
use Kern\Slug\Models\Slug;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
    }

    protected function enableInertia(): void
    {
        config(['core.base.admin.modules.pages' => 'inertia']);
    }

    protected function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_admin_routes_stay_react_when_global_legacy_mode_is_set(): void
    {
        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Auth/Form');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('pages.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Index');
        $this->assertNull(app(AdminRenderer::class)->fallbackReason('pages'));

        config(['core.base.admin.legacy_mode' => true]);
        foreach (['access.login', 'dashboard.index', 'pages.index', 'settings.general', 'system.index', 'media.index'] as $route) {
            $this->assertTrue(app(AdminRenderer::class)->isInertiaRoute($route), $route.' must remain a React admin route.');
        }

        $this->get(route('pages.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Index');
        $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Dashboard/Index');
        $this->get(route('pages.create'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Form');
        $this->assertNull(session('error_msg'), 'Global rollback should not flash an automatic fallback warning.');
    }

    public function test_global_legacy_mode_keeps_guest_authentication_on_react(): void
    {
        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Auth/Form');

        config(['core.base.admin.legacy_mode' => true]);

        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Auth/Form');
    }

    public function test_page_table_restores_template_and_configured_tools_and_exports_filtered_rows(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['pages.index' => true]]);
        $this->actingAs($user);
        config(['packages.page.general.templates' => ['default' => 'Default layout', 'landing' => 'Landing layout']]);
        $secondIncluded = PageFactory::new()->create(['name' => 'Zebra export page', 'template' => 'landing']);
        $included = PageFactory::new()->create(['name' => 'Included export page', 'template' => 'landing', 'content' => 'Private content must not be exported']);
        PageFactory::new()->create(['name' => 'Excluded export page', 'template' => 'default']);
        Setting::set(['datatables_default_show_export_button' => false, 'datatables_default_show_column_visibility' => false])->save();
        $this->get(route('pages.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.tableButtons', ['reload']);
        $this->get(route('pages.index', ['_export' => 'csv']))->assertForbidden();

        Setting::set(['datatables_default_show_export_button' => true, 'datatables_default_show_column_visibility' => true])->save();
        $this->putJson(route('table.update-columns-visibility'), ['table' => PageTable::class, 'columns_visibility' => ['template' => '0']])
            ->assertOk()->assertJsonPath('error', false);
        $this->actingAs($user->fresh());
        $this->get(route('pages.index', ['template' => 'landing']), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.tableButtons', ['reload', 'export', 'visibility'])
            ->assertJsonPath('props.tableVisibility.columns.template', 0)
            ->assertJsonPath('props.pages.data.0.template_label', 'Landing layout');

        foreach (['csv', 'xlsx'] as $format) {
            $response = $this->get(route('pages.index', ['_export' => $format, 'template' => 'landing', 'sort' => 'name', 'direction' => 'asc']))->assertOk()->assertHeader('content-disposition');
            $path = $response->baseResponse->getFile()->getPathname();
            $rows = IOFactory::load($path)->getActiveSheet()->toArray();
            if ($format === 'csv') {
                $csvRows = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                $this->assertSame([5, 5, 5], array_map('count', $csvRows));
            }
            $this->assertSame(['ID', 'Name', 'Template', 'Created At', 'Status'], $rows[0]);
            $this->assertCount(3, $rows);
            $this->assertCount(5, $rows[2]);
            $this->assertSame((string) $secondIncluded->getKey(), (string) $rows[2][0]);
            $this->assertCount(5, $rows[1]);
            $this->assertSame((string) $included->getKey(), (string) $rows[1][0]);
            $text = json_encode($rows);
            $this->assertStringNotContainsString('Private content must not be exported', $text);
            $this->assertStringContainsString('Included export page', $text);
            $this->assertStringNotContainsString('Excluded export page', $text);
        }
        $this->getJson(route('pages.index', ['_export' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('_export');
        Setting::set('datatables_default_show_column_visibility', false)->save();
        $this->get(route('pages.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.tableVisibility.columns', []);
        $this->actingAs(UserFactory::new()->create());
        $this->getJson(route('pages.index', ['_export' => 'csv']))->assertUnauthorized();
    }

    public function test_page_content_and_metadata_roundtrip_while_global_legacy_mode_is_set(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $content = '<p class="legacy-content">Tiếng Việt [example custom="retained"]Body[/example]</p>';
        $seo = ['seo_title' => 'Tiêu đề SEO', 'seo_description' => 'Mô tả giữ nguyên'];

        $this->post(route('pages.store'), [
            'name' => 'React rollback fixture', 'status' => 'published', 'content' => $content,
            'slug' => 'rollback-fixture', 'is_slug_editable' => 1, 'seo_meta' => $seo,
            'submitter' => 'save',
        ], $this->inertiaHeaders())->assertRedirect(route('pages.index'));
        $page = Page::query()->where('name', 'React rollback fixture')->firstOrFail();

        config(['core.base.admin.legacy_mode' => true]);
        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Form');
        $this->post(route('pages.update', $page), [
            'name' => 'Edited with Blade', 'status' => 'published', 'content' => $content,
            'slug' => 'rollback-fixture', 'is_slug_editable' => 1, 'seo_meta' => $seo,
        ], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Edited with Blade', $page->refresh()->name);

        config(['core.base.admin.legacy_mode' => false]);
        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('component', 'Pages/Form')
            ->assertJsonPath('props.page.name', 'Edited with Blade')
            ->assertJsonPath('props.page.content', $content);
        $this->post(route('pages.update', $page), [
            'name' => 'Returned to React', 'status' => 'published', 'submitter' => 'apply',
        ], $this->inertiaHeaders())->assertRedirect(route('pages.edit', $page))->assertSessionHasNoErrors();

        $this->assertSame('Returned to React', $page->refresh()->name);
        $this->assertSame($content, $page->content);
        $this->assertSame($seo, MetaBox::getMetaData($page, 'seo_meta', true));
        $this->assertDatabaseHas('slugs', ['reference_id' => $page->getKey(), 'reference_type' => Page::class, 'key' => 'rollback-fixture']);
        $this->assertSame(1, Page::query()->count());
    }

    public function test_page_permalink_exposes_draft_preview_and_readonly_homepage_url(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $page = PageFactory::new()->create(['status' => 'draft']);

        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.permalink.preview', true)
            ->assertJsonPath('props.permalink.homepage', null)
            ->assertJsonPath('props.permalink.model', Page::class);
        Setting::set('show_on_front', $page->getKey())->save();
        add_filter('cms_homepage_url', fn (): string => url('/'));
        $page->update(['status' => 'published']);
        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.permalink.homepage', url('/'))
            ->assertJsonPath('props.permalink.preview', false);
    }

    public function test_global_legacy_mode_does_not_block_react_page_writes(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $page = PageFactory::new()->create(['name' => 'Original page']);
        config(['core.base.admin.legacy_mode' => true]);
        $this->post(route('pages.update', $page), ['name' => 'React update'], $this->inertiaHeaders())->assertRedirect();
        $this->assertSame('React update', $page->refresh()->name);
        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Form');
    }

    public function test_inertia_pages_support_filter_sort_and_pagination_without_html_columns(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        PageFactory::new()->create(['name' => 'Alpha Kern']);
        PageFactory::new()->create(['name' => 'Beta Kern']);
        PageFactory::new()->create(['name' => 'Other']);
        $this->get(route('pages.index', ['search' => 'kErN', 'status' => 'published', 'sort' => 'name', 'direction' => 'desc', 'per_page' => 1, 'page' => 2]), $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('component', 'Pages/Index')
            ->assertJsonPath('props.pages.total', 2)->assertJsonPath('props.pages.data.0.name', 'Alpha Kern')
            ->assertJsonPath('props.permissions.create', true);
        $this->get(route('pages.create'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Form');
    }

    public function test_inertia_page_save_preserves_html_whitelists_model_fields_and_runs_metadata_once(): void
    {
        $this->enableInertia();
        $user = UserFactory::new()->superUser()->create();
        $this->actingAs($user);
        $created = 0;
        $updated = 0;
        Event::listen(CreatedContentEvent::class, function ($event) use (&$created): void {
            if ($event->data instanceof Page) {
                $created++;
            }
        });
        Event::listen(UpdatedContentEvent::class, function ($event) use (&$updated): void {
            if ($event->data instanceof Page) {
                $updated++;
            }
        });
        $html = '<table class="legacy"><tr><td style="color:red">Content [example][/example]</td></tr></table>';
        $this->post(route('pages.store'), [
            'name' => 'React page', 'status' => 'published', 'content' => $html,
            'slug' => 'react-page', 'is_slug_editable' => 1,
            'seo_meta' => ['seo_title' => 'SEO title', 'seo_description' => 'SEO description'],
            'user_id' => 999999, 'submitter' => 'save',
        ], $this->inertiaHeaders())->assertRedirect(route('pages.index'));
        $page = Page::query()->firstOrFail();
        $this->assertSame($user->getKey(), $page->user_id);
        $this->assertSame($html, $page->content);
        $this->assertSame(1, $created);
        $this->assertSame(1, Slug::query()->where('reference_id', $page->getKey())->count());
        $this->assertSame('SEO title', MetaBox::getMetaData($page, 'seo_meta', true)['seo_title']);
        $this->post(route('pages.update', $page), ['name' => 'Updated title', 'status' => 'published', 'submitter' => 'apply'], $this->inertiaHeaders())
            ->assertRedirect(route('pages.edit', $page));
        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('revisions', ['revisionable_id' => $page->getKey(), 'revisionable_type' => Page::class, 'key' => 'name', 'old_value' => 'React page', 'new_value' => 'Updated title']);
        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.revisions.0.field', 'name')->assertJsonPath('props.revisions.0.old_value', 'React page');
        $revisionCount = Revision::query()->where('revisionable_id', $page->getKey())->count();
        $this->post(route('pages.update', $page), ['name' => 'Updated title', 'status' => 'published'], $this->inertiaHeaders())->assertRedirect();
        $this->assertSame($revisionCount, Revision::query()->where('revisionable_id', $page->getKey())->count());
        $this->assertDatabaseHas('pages', ['id' => $page->getKey(), 'name' => 'Updated title']);
        $this->delete(route('pages.destroy', $page), [], $this->inertiaHeaders())->assertRedirect(route('pages.index'));
        $this->assertDatabaseMissing('pages', ['id' => $page->getKey()]);
    }

    public function test_inertia_validation_redirects_and_permissions_return_an_error_page(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->from(route('pages.create'))->post(route('pages.store'), ['name' => '', 'slug' => '<invalid>'], $this->inertiaHeaders())
            ->assertRedirect(route('pages.create'))->assertSessionHasErrors(['name', 'slug']);
        $this->assertDatabaseCount('pages', 0);
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('pages.index'), $this->inertiaHeaders())->assertForbidden()->assertJsonPath('component', 'Pages/Error');
    }

    public function test_initial_admin_error_visits_use_inertia_shell_and_public_errors_remain_public(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->create());
        Route::middleware(['web', HandleAdminInertiaRequests::class.':pages'])
            ->get('/admin/forbidden-screen', fn () => abort(403))
            ->name('acceptance.forbidden-screen');
        $forbidden = $this->get('/admin/forbidden-screen')->assertForbidden()->assertViewIs('core/base::admin.app');
        $forbidden->assertViewHas('page', fn (array $page): bool => $page['component'] === 'Pages/Error' && $page['props']['status'] === 403);

        config(['core.base.general.admin_dir' => 'control']);
        $missing = $this->get('/control/unknown-screen')->assertNotFound()->assertViewIs('core/base::admin.app');
        $missing->assertViewHas('page', fn (array $page): bool => $page['component'] === 'Pages/Error' && $page['props']['status'] === 404);
        $this->get('/missing-kern-page')->assertNotFound();
    }

    public function test_bulk_changes_check_permissions_and_keep_blade_available(): void
    {
        $this->enableInertia();
        $page = PageFactory::new()->create();
        $updates = 0;
        $deletions = 0;
        Event::listen(UpdatedContentEvent::class, function ($event) use (&$updates): void {
            if ($event->data instanceof Page) {
                $updates++;
            }
        });
        Event::listen(DeletedContentEvent::class, function ($event) use (&$deletions): void {
            if ($event->data instanceof Page) {
                $deletions++;
            }
        });
        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.index' => true]]));
        $this->post(route('pages.bulk'), ['ids' => [$page->getKey()], 'action' => 'delete'], $this->inertiaHeaders())->assertForbidden();
        $this->assertDatabaseHas('pages', ['id' => $page->getKey()]);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->post(route('pages.bulk'), ['ids' => [$page->getKey()], 'action' => 'update', 'field' => 'status', 'value' => 'draft'], $this->inertiaHeaders())
            ->assertRedirect(route('pages.index'));
        $this->assertDatabaseHas('pages', ['id' => $page->getKey(), 'status' => 'draft']);
        $this->post(route('pages.bulk'), ['ids' => [$page->getKey()], 'action' => 'delete'], $this->inertiaHeaders())->assertRedirect();
        $this->assertDatabaseMissing('pages', ['id' => $page->getKey()]);
        $this->assertSame(1, $updates);
        $this->assertSame(1, $deletions);
    }

    public function test_profile_input_cannot_grant_permissions(): void
    {
        $user = UserFactory::new()->create(['username' => 'regular-user']);
        $this->actingAs($user)->putJson(route('users.update-profile', $user), [
            'username' => $user->username, 'first_name' => 'Regular', 'last_name' => 'User', 'email' => $user->email,
            'permissions' => ['superuser' => true], 'super_user' => true, 'manage_supers' => true,
        ])->assertOk();
        $this->assertFalse($user->fresh()->isSuperUser());
    }

    public function test_private_media_requires_permission_and_ownership(): void
    {
        $this->assertContains('web', Route::getRoutes()->getByName('media.indirect.url')->gatherMiddleware());
        Storage::fake('local');
        Storage::disk('local')->put('private.txt', 'secret');
        $owner = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $file = MediaFile::query()->create(['name' => 'Private', 'url' => 'private.txt', 'mime_type' => 'text/plain', 'size' => 6, 'visibility' => 'private', 'user_id' => $owner->getKey()]);
        $this->get($file->indirect_url)->assertForbidden();
        Setting::set('user_can_only_view_own_media', true)->save();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['media.index' => true]]))->get($file->indirect_url)->assertForbidden();
        $this->actingAs($owner)->get($file->indirect_url)->assertDownload('private.txt');
    }

    public function test_unknown_extensions_render_compatibility_screen_instead_of_running_blade(): void
    {
        $this->enableInertia();
        config(['core.base.admin.supported_plugins.pages' => []]);
        app(PluginService::class)->activate('block');
        $this->actingAs(UserFactory::new()->superUser()->create())->get(route('pages.index'), $this->inertiaHeaders())
            ->assertStatus(503)->assertJsonPath('component', 'System/Compatibility')
            ->assertJsonPath('props.title', 'Admin integration requires an update');
    }

    public function test_active_extensions_translate_pages_and_save_custom_fields_without_changing_default_content(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $service = app(PluginService::class);
        $service->activate('language');
        $service->activate('language-advanced');
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $result = $service->activate($plugin);
                $this->assertFalse($result['error'], $plugin);
            }
        }
        Language::query()->create(['lang_name' => 'English', 'lang_locale' => 'en', 'lang_code' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us', 'lang_order' => 1]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_locale' => 'vi', 'lang_code' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn', 'lang_order' => 2]);
        \Kern\Language\Facades\Language::setSupportedLocales([
            'en' => ['lang_name' => 'English', 'lang_code' => 'en', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'],
            'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn'],
        ]);
        $group = FieldGroup::query()->create(['title' => 'Page details', 'status' => 'published', 'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Page::class]]])]);
        $field = FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Subtitle', 'slug' => 'subtitle', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->post(route('pages.store'), ['name' => 'Default page', 'status' => 'published', 'content' => '<p>English</p>', 'slug' => 'default-page', 'is_slug_editable' => 1], $this->inertiaHeaders())->assertRedirect();
        $page = Page::query()->firstOrFail();
        $response = $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Form');
        $groups = $response->json('props.customFieldGroups');
        $this->assertCount(1, $groups);
        $groups[0]['items'][0]['value'] = 'Page subtitle';
        $groups[0]['items'][0]['slug'] = 'attacker-controlled';
        $this->post(route('pages.update', $page), ['name' => 'Default page', 'status' => 'published', 'custom_fields' => json_encode($groups), 'gallery' => json_encode([['img' => 'https://example.test/default.jpg', 'description' => 'Default gallery']])], $this->inertiaHeaders())->assertRedirect();
        $this->assertDatabaseHas('custom_fields', ['use_for_id' => $page->getKey(), 'field_item_id' => $field->getKey(), 'slug' => 'subtitle', 'value' => 'Page subtitle']);
        $this->assertSame('Default gallery', GalleryMeta::query()->where('reference_id', $page->getKey())->firstOrFail()->images[0]['description']);
        $response = $this->get(route('pages.edit', $page).'?ref_lang=vi', $this->inertiaHeaders())->assertOk()->assertJsonPath('props.translatable', true)->assertJsonPath('props.language', 'vi');
        $this->post(route('pages.update', $page).'?ref_lang=vi', ['name' => 'Trang tiếng Việt', 'description' => 'Mô tả', 'content' => '<p>Tiếng Việt</p>', 'slug' => 'trang-tieng-viet', 'language' => 'vi', 'status' => 'draft', 'template' => 'default', 'seo_meta' => ['seo_title' => 'Tiêu đề', 'index' => 'noindex'], 'gallery' => json_encode([['img' => 'https://example.test/translated.jpg', 'description' => 'Translated gallery']])], $this->inertiaHeaders())->assertRedirect();
        $this->assertDatabaseHas('pages', ['id' => $page->getKey(), 'name' => 'Default page', 'status' => 'published', 'content' => '<p>English</p>']);
        $this->assertDatabaseHas('pages_translations', ['pages_id' => $page->getKey(), 'lang_code' => 'vi', 'name' => 'Trang tiếng Việt']);
        $this->assertDatabaseHas('slugs_translations', ['lang_code' => 'vi', 'key' => 'trang-tieng-viet']);
        $this->assertDatabaseHas('meta_boxes', ['reference_id' => $page->getKey(), 'meta_key' => 'vi_seo_meta']);
        $this->get(route('pages.index', ['ref_lang' => 'vi', 'search' => 'Trang']), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.pages.total', 1)->assertJsonPath('props.pages.data.0.name', 'Trang tiếng Việt')->assertJsonPath('props.pages.data.0.edit_url', route('pages.edit', $page).'?ref_lang=vi');
        $this->get(route('pages.edit', $page).'?ref_lang=vi', $this->inertiaHeaders())->assertOk()->assertJsonPath('props.page.seo_meta.seo_title', 'Tiêu đề')->assertJsonPath('props.gallery.0.description', 'Translated gallery');
        $this->post(route('pages.bulk').'?ref_lang=vi', ['ids' => [$page->getKey()], 'action' => 'update', 'field' => 'status', 'value' => 'draft'], $this->inertiaHeaders())->assertSessionHasErrors('field');
        $this->post(route('pages.bulk').'?ref_lang=vi', ['ids' => [$page->getKey()], 'action' => 'update', 'field' => 'name', 'value' => 'Tên hàng loạt'], $this->inertiaHeaders())->assertRedirect(route('pages.index').'?ref_lang=vi');
        $this->assertDatabaseHas('pages', ['id' => $page->getKey(), 'name' => 'Default page', 'status' => 'published']);
        $this->assertDatabaseHas('pages_translations', ['pages_id' => $page->getKey(), 'lang_code' => 'vi', 'name' => 'Tên hàng loạt']);
        $this->delete(route('pages.destroy', $page).'?ref_lang=vi', [], $this->inertiaHeaders())->assertRedirect(route('pages.index').'?ref_lang=vi');
        $this->assertDatabaseMissing('pages', ['id' => $page->getKey()]);
    }

    public function test_registered_page_filters_combine_name_and_date_and_reject_unknown_fields_and_operators(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        PageFactory::new()->create(['name' => 'Target page', 'created_at' => '2025-01-02 12:00:00']);
        $matched = PageFactory::new()->create(['name' => 'Target page', 'created_at' => '2026-02-03 12:00:00']);
        PageFactory::new()->create(['name' => 'Other page', 'created_at' => '2026-02-03 12:00:00']);
        $advanced = [['field' => 'name', 'operator' => '=', 'value' => 'Target page'], ['field' => 'created_at', 'operator' => '>=', 'value' => '2026-01-01']];

        $response = $this->get(route('pages.index', ['advanced' => $advanced]), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.pages.total', 1)->assertJsonPath('props.pages.data.0.id', (string) $matched->id)
            ->assertJsonPath('props.filters.advanced', $advanced);
        $this->assertSame(['name', 'template', 'status', 'created_at'], array_column($response->json('props.filterFields'), 'name'));
        foreach ([['field' => 'user_id', 'operator' => '=', 'value' => '1'], ['field' => 'name', 'operator' => 'or 1=1', 'value' => 'Target page'], ['field' => 'created_at', 'operator' => '>=', 'value' => 'invalid-date']] as $filter) {
            $this->getJson(route('pages.index', ['advanced' => [$filter]]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('pages', 3);
    }

    public function test_page_picker_uses_folder_aware_media_contract_and_hides_inaccessible_controls(): void
    {
        $this->enableInertia();
        $user = UserFactory::new()->create(['permissions' => ['pages.create' => true, 'media.index' => true]]);
        $this->actingAs($user);
        Setting::set('user_can_only_view_own_media', true)->save();
        $folder = MediaFolder::query()->create(['name' => 'Page images', 'slug' => 'page-images', 'parent_id' => 0, 'user_id' => $user->id]);
        MediaFolder::query()->create(['name' => 'Other folder', 'slug' => 'other-folder', 'parent_id' => 0, 'user_id' => UserFactory::new()->create()->id]);
        MediaFile::query()->create(['name' => 'Page image', 'url' => 'page-images/cover.png', 'mime_type' => 'image/png', 'visibility' => 'public', 'folder_id' => $folder->id, 'user_id' => $user->id, 'size' => 10]);

        $response = $this->get(route('pages.create'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.urls.media', route('media.picker'))->assertJsonPath('props.urls.mediaUpload', route('media.picker-upload'))
            ->assertJsonPath('props.admin.capabilities.browseMedia', true)->assertJsonPath('props.admin.capabilities.uploadMedia', true);
        $this->getJson($response->json('props.urls.media').'?folder_id=0&type=image')->assertOk()->assertJsonCount(1, 'folders')->assertJsonPath('folders.0.id', (string) $folder->id)->assertJsonCount(0, 'data');
        $this->getJson($response->json('props.urls.media').'?folder_id='.$folder->id.'&type=image')->assertOk()->assertJsonPath('data.0.name', 'Page image')->assertJsonPath('breadcrumbs.0.name', 'Page images');

        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.create' => true]]));
        $this->get(route('pages.create'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.capabilities.browseMedia', false)->assertJsonPath('props.admin.capabilities.uploadMedia', false);
        $this->getJson(route('media.picker'))->assertUnauthorized();
    }

    public function test_media_picker_respects_own_media_and_bulk_does_not_accept_metadata_injection(): void
    {
        $this->enableInertia();
        $user = UserFactory::new()->create(['permissions' => ['pages.index' => true, 'pages.edit' => true, 'media.index' => true]]);
        $this->actingAs($user);
        Setting::set('user_can_only_view_own_media', true)->save();
        MediaFile::query()->create(['name' => 'Owned', 'url' => 'owned.png', 'mime_type' => 'image/png', 'visibility' => 'public', 'user_id' => $user->getKey(), 'size' => 10]);
        MediaFile::query()->create(['name' => 'Other', 'url' => 'other.png', 'mime_type' => 'image/png', 'visibility' => 'public', 'user_id' => UserFactory::new()->create()->getKey(), 'size' => 10]);
        $this->getJson(route('pages.media', ['search' => 'oWnEd', 'type' => 'image']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Owned');
        $this->getJson(route('pages.media', ['search' => 'Missing']))->assertOk()->assertJsonPath('total', 0);
        $page = PageFactory::new()->create();
        MetaBox::saveMetaBoxData($page, 'seo_meta', ['seo_title' => 'Original']);
        $this->post(route('pages.bulk'), ['ids' => [$page->getKey()], 'action' => 'update', 'field' => 'status', 'value' => 'draft', 'seo_meta' => ['seo_title' => 'Injected'], 'slug' => 'injected', 'is_slug_editable' => 1], $this->inertiaHeaders())->assertRedirect();
        $this->assertSame('Original', MetaBox::getMetaData($page->fresh(), 'seo_meta', true)['seo_title']);
        $this->assertDatabaseMissing('slugs', ['reference_id' => $page->getKey(), 'key' => 'injected']);
    }

    public function test_pages_created_in_inertia_render_on_the_public_theme(): void
    {
        $result = app(ThemeService::class)->activate('ripple');
        $this->assertFalse($result['error']);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $response = $this->get(route('pages.create'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Pages/Form');
        $schemas = collect($response->json('props.shortcodes'))->keyBy('key');
        $this->assertTrue($schemas['featured-posts']['supported']);
        $this->assertNotEmpty($schemas['featured-posts']['fields']);
        $this->assertNotEmpty($schemas['featured-posts']['description']);
        $this->assertNotEmpty($schemas['featured-posts']['previewImage']);
        $blogFields = collect($schemas['blog-posts']['fields'])->keyBy('name');
        $this->assertTrue($blogFields->has('category_ids'), json_encode($schemas['blog-posts']));
        $this->assertTrue($blogFields['category_ids']['multiple']);
        $this->assertSame('no', $blogFields['enable_lazy_loading']['value']);
        $this->post(route('pages.store'), ['name' => 'Public React page', 'banner_image' => 'https://example.test/banner.jpg', 'content' => '<p>Visible public content</p>', 'status' => 'published', 'slug' => 'public-react-page', 'is_slug_editable' => 1], $this->inertiaHeaders())->assertRedirect();
        $this->assertDatabaseHas('meta_boxes', ['reference_type' => Page::class, 'meta_key' => 'banner_image']);
        $this->get(route('pages.edit', Page::query()->firstOrFail()), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.bannerEnabled', true)->assertJsonPath('props.page.banner_image', 'https://example.test/banner.jpg');
        $this->get('/public-react-page')->assertOk()->assertSee('Visible public content')->assertDontSee('resources/js/admin.tsx');
    }

    public function test_admin_prefix_and_locale_are_shared_without_changing_public_routes(): void
    {
        $this->enableInertia();
        config(['core.base.general.admin_dir' => 'control/office']);
        AdminHelper::registerRoutes(function (): void {
            Route::get('pages', [PageController::class, 'index'])
                ->middleware(HandleAdminInertiaRequests::class)->name('pages.index')->permission('pages.index');
        });
        Route::getRoutes()->refreshNameLookups();
        config(['core.base.general.locale' => 'vi']);
        Setting::set('locale', 'vi')->save();
        app()->setLocale('vi');
        $this->withSession(['site-locale' => 'vi']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get('/control/office/pages', $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.locale', 'vi')->assertJsonPath('props.admin.translations.Pages', 'Trang')
            ->assertJsonPath('props.urls.index', route('pages.index'));
        $this->get('/')->assertRedirect(route('dashboard.index'));
    }

    public function test_admin_shared_timezone_matches_application_timezone_instead_of_browser_timezone(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        foreach (['UTC', 'Asia/Ho_Chi_Minh', 'America/Los_Angeles'] as $timezone) {
            config(['app.timezone' => $timezone]);
            $this->get(route('pages.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.admin.timeZone', $timezone);
        }
    }

    public function test_unsupported_shortcode_configuration_blocks_render_and_saving(): void
    {
        $this->enableInertia();
        shortcode()->register('legacy-config', 'Legacy configuration', null, fn () => '');
        shortcode()->setAdminConfig('legacy-config', fn () => '<input name="legacy">');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('pages.create'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->post(route('pages.store'), ['name' => 'Should reload Blade'], $this->inertiaHeaders())->assertStatus(503);
        $this->assertDatabaseMissing('pages', ['name' => 'Should reload Blade']);
    }

    public function test_custom_field_schema_respects_template_and_user_roles_and_rejects_unknown_fields(): void
    {
        app(PluginService::class)->activate('custom-field');
        $this->enableInertia();
        $user = UserFactory::new()->superUser()->create();
        $this->actingAs($user);
        $restrictedRole = Role::query()->create(['name' => 'Restricted', 'slug' => 'restricted', 'created_by' => $user->getKey(), 'updated_by' => $user->getKey()]);
        $restricted = FieldGroup::query()->create(['title' => 'Restricted group', 'status' => 'published', 'rules' => json_encode([[['name' => 'logged_in_user_has_role', 'type' => '==', 'value' => $restrictedRole->getKey()]]])]);
        $conditional = FieldGroup::query()->create(['title' => 'Template group', 'status' => 'published', 'rules' => json_encode([[['name' => 'page_template', 'type' => '==', 'value' => 'landing']]])]);
        $field = FieldItem::query()->create(['field_group_id' => $conditional->getKey(), 'title' => 'Subtitle', 'slug' => 'subtitle', 'type' => 'text', 'options' => '{}']);
        config(['packages.page.general.templates' => ['default' => 'Default', 'landing' => 'Landing']]);
        $page = PageFactory::new()->create();
        $this->get(route('pages.edit', $page), $this->inertiaHeaders())->assertOk()->assertJsonCount(0, 'props.customFieldGroups');
        $this->get(route('pages.edit', $page).'?template=landing', $this->inertiaHeaders())->assertOk()->assertJsonCount(1, 'props.customFieldGroups')->assertJsonPath('props.customFieldGroups.0.title', 'Template group');
        $this->post(route('pages.update', $page), ['name' => 'Invalid custom field', 'status' => 'published', 'template' => 'landing', 'custom_fields' => json_encode([['id' => $conditional->getKey(), 'items' => [['id' => 'unknown', 'value' => 'Injected']]]])], $this->inertiaHeaders())->assertSessionHasErrors('custom_fields');
        $this->assertDatabaseMissing('pages', ['name' => 'Invalid custom field']);
        $this->assertDatabaseCount('custom_fields', 0);
    }

    public function test_unknown_page_form_extension_uses_compatibility_screen(): void
    {
        $this->enableInertia();
        PageForm::extend(function (PageForm $form): void {
            $form->add('unsupported_extension', TextField::class);
        });
        $this->actingAs(UserFactory::new()->superUser()->create())->get(route('pages.create'), $this->inertiaHeaders())->assertStatus(503)
            ->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_registered_plugin_cannot_bypass_unsupported_page_form_compatibility_guard(): void
    {
        $this->enableInertia();
        app(PluginService::class)->activate('block');
        $this->assertContains('block', get_active_plugins());
        config(['core.base.admin.supported_plugins.pages' => []]);
        $this->mock(AdminExtensionRegistry::class)->shouldReceive('supports')->with('block', 'pages')->atLeast()->once()->andReturnTrue();
        PageForm::extend(function (PageForm $form): void {
            $form->add('unsupported_extension', TextField::class);
        });

        $this->actingAs(UserFactory::new()->superUser()->create())->get(route('pages.create'), $this->inertiaHeaders())->assertStatus(503)
            ->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_page_rendering_hooks_run_once_per_schema_and_unknown_meta_boxes_are_blocked(): void
    {
        $this->enableInertia();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $calls = 0;
        PageForm::beforeRendering(function (PageForm $form) use (&$calls): void {
            $calls++;
            $form->addMetaBoxes(['legacy_widget' => ['title' => 'Legacy', 'content' => '<input name="legacy">']]);
        });
        $form = PageForm::create();
        $form->prepareForSchema()->prepareForSchema();
        $this->assertSame(1, $calls);
        $this->get(route('pages.create'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->post(route('pages.store'), ['name' => 'Rejected extension write'], $this->inertiaHeaders())->assertStatus(503);
        $this->assertDatabaseMissing('pages', ['name' => 'Rejected extension write']);
    }

    public function test_inertia_guest_and_expired_session_responses_are_navigable(): void
    {
        $this->enableInertia();
        $this->get(route('pages.index'), $this->inertiaHeaders())->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
        Route::get('/admin/pages/expired-session', fn () => abort(419))->name('pages.expired-session')->middleware('web');
        $this->get('/admin/pages/expired-session', $this->inertiaHeaders())->assertStatus(419)->assertJsonPath('component', 'Pages/Error')->assertJsonPath('props.status', 419);
    }
}
