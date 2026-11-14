<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Enums\UserStatusEnum;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\ACL\Models\UserMeta;
use Kern\ACL\Tables\RoleTable;
use Kern\ACL\Tables\UserTable;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Models\AdminNotification;
use Kern\Blog\Models\Tag;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\Language\Models\Language;
use Kern\Media\Models\MediaFolder;
use Kern\Page\Tables\PageTable;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;
use ZipArchive;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminEndpointAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsesTestingDatabase();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    public function test_legacy_catalog_count_role_and_table_endpoints_return_their_expected_contracts(): void
    {
        app(PluginService::class)->activate('blog');
        Tag::query()->create(['name' => 'Endpoint tag', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $this->getJson(route('tags.all'))->assertOk()->assertExactJson(['Endpoint tag']);
        $this->getJson(route('core-icons', ['per_page' => 2]))->assertOk()->assertJsonPath('error', false)->assertJsonCount(2, 'data.data');
        $this->getJson(route('menu-items-count'))->assertOk()->assertJsonPath('error', false)->assertJsonStructure(['data']);
        $role = Role::query()->create(['name' => 'Endpoint role', 'permissions' => [], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        $this->getJson(route('roles.list.json'))->assertOk()->assertExactJson([['value' => $role->id, 'text' => 'Endpoint role']]);
        $this->getJson(route('table.bulk-change.data', ['class' => PageTable::class, 'key' => 'status']))->assertOk()->assertJsonPath('type', 'customSelect')->assertJsonStructure(['choices' => ['published'], 'validation']);
        $this->getJson(route('table.bulk-change.data', ['class' => EndpointLegacyBulkTable::class, 'key' => 'legacy']))->assertOk()->assertJsonPath('label', 'Legacy field')->assertJsonPath('validation', 'required|string');
        $this->getJson(route('table.bulk-change.data', ['class' => 'MissingEndpointTable', 'key' => 'status']))->assertOk()->assertExactJson([]);
        $this->getJson(route('table.bulk-change.data', ['class' => PageTable::class, 'key' => 'unknown']))->assertOk()->assertJsonPath('type', 'text')->assertJsonPath('choices', []);
        $this->getJson(route('table.bulk-change.data', ['key' => 'status']))->assertUnprocessable()->assertJsonValidationErrors('class');
        $this->getJson(route('table.filter.input', ['class' => PageTable::class, 'key' => 'name', 'value' => 'Endpoint filter']))->assertOk()->assertJsonPath('value', 'Endpoint filter')->assertJsonStructure(['type', 'choices']);
        $this->get('/admin/plugins')->assertRedirect(route('plugins.index'));
    }

    public function test_media_legacy_folder_breadcrumb_and_popup_endpoints_work_with_owned_fixtures(): void
    {
        Storage::fake('public');
        $root = MediaFolder::query()->create(['name' => 'Endpoint root', 'slug' => 'endpoint-root', 'parent_id' => 0, 'user_id' => auth()->id()]);
        $child = MediaFolder::query()->create(['name' => 'Endpoint child', 'slug' => 'endpoint-child', 'parent_id' => $root->id, 'user_id' => auth()->id()]);
        $this->getJson(route('media.folder-picker'))->assertOk()->assertJsonCount(2)->assertJsonFragment(['name' => 'Endpoint child', 'parent_id' => $root->id]);
        $this->getJson(route('media.breadcrumbs', ['folder_id' => $child->id]))->assertOk()->assertJsonFragment(['name' => 'Endpoint root'])->assertJsonFragment(['name' => 'Endpoint child']);
        $this->get(route('media.popup'), ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))])->assertOk()->assertJsonPath('component', 'Media/Index');
    }

    public function test_plugin_alias_redirect_respects_custom_admin_prefix(): void
    {
        app('router')->getRoutes()->getByName('plugins.index')->setUri('acceptance-admin/plugins/installed');
        $this->assertSame('/acceptance-admin/plugins/installed', parse_url(route('plugins.index'), PHP_URL_PATH));
        $this->get('/admin/plugins')->assertRedirect(route('plugins.index'));
    }

    public function test_read_only_actor_cannot_mutate_page_through_legacy_bulk_endpoint(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['pages.index' => true, 'users.index' => true]]);
        $page = PageFactory::new()->create(['name' => 'Protected page']);
        $this->actingAs($actor);
        $response = $this->postJson(route('table.bulk-change.save'), ['class' => PageTable::class, 'key' => 'name', 'value' => 'Unauthorized page rename', 'ids' => [(string) $page->id]]);
        $this->assertSame('Protected page', $page->fresh()->name, 'Read-only actor changed a Page through the generic legacy endpoint.');
        $response->assertForbidden();
    }

    public function test_read_only_actor_cannot_mutate_user_through_legacy_bulk_endpoint(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['users.index' => true]]);
        $target = UserFactory::new()->create(['email' => 'protected@example.test']);
        $this->actingAs($actor);
        $response = $this->postJson(route('table.bulk-change.save'), ['class' => UserTable::class, 'key' => 'email', 'value' => 'unauthorized@example.test', 'ids' => [(string) $target->id]]);
        $this->assertSame('protected@example.test', $target->fresh()->email, 'Read-only actor changed a User through the generic legacy endpoint.');
        $response->assertForbidden();
    }

    public function test_authorized_legacy_bulk_changes_preserve_events_and_reject_unknown_fields_ids_and_demo_writes(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['pages.edit' => true, 'users.edit' => true]]);
        $page = PageFactory::new()->create(['name' => 'Original page']);
        $target = UserFactory::new()->create(['email' => 'before@example.test']);
        $updates = 0;
        Event::listen(UpdatedContentEvent::class, function () use (&$updates): void {
            $updates++;
        });
        $this->actingAs($actor);
        $url = route('table.bulk-change.save');
        $payload = ['class' => PageTable::class, 'key' => 'name', 'value' => 'Authorized page', 'ids' => [(string) $page->id]];
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('error', false);
        $this->postJson($url, ['class' => UserTable::class, 'key' => 'email', 'value' => 'after@example.test', 'ids' => [(string) $target->id]])->assertOk()->assertJsonPath('error', false);
        $this->assertSame('Authorized page', $page->fresh()->name);
        $this->assertSame('after@example.test', $target->fresh()->email);
        $this->assertSame(2, $updates);
        foreach ([['key' => 'permissions'], ['ids' => [(string) $page->id, (string) $page->id]], ['ids' => [(string) $page->id, '999999']]] as $invalid) {
            $this->postJson($url, [...$payload, ...$invalid, 'value' => 'Rejected'])->assertUnprocessable();
            $this->assertSame('Authorized page', $page->fresh()->name);
        }
        $this->postJson($url, [...$payload, 'class' => \stdClass::class])->assertForbidden();
        $this->postJson($url, [...$payload, 'class' => EndpointNoEditBulkTable::class])->assertForbidden();
        $this->postJson($url, [...$payload, 'class' => EndpointCanceledBulkTable::class])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->assertSame('Authorized page', $page->fresh()->name);
        config(['core.base.general.demo_mode_enabled' => true]);
        $this->postJson($url, [...$payload, 'value' => 'Demo write'])->assertForbidden();
        $this->assertSame('Authorized page', $page->fresh()->name);
        $this->assertSame(2, $updates);
    }

    public function test_legacy_bulk_guards_self_protected_superuser_and_ungrantable_role_before_writes(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['users.edit' => true, 'roles.edit' => true]]);
        $super = UserFactory::new()->superUser()->create(['email' => 'super@example.test']);
        $role = Role::query()->create(['name' => 'Higher role', 'permissions' => ['pages.index' => true], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->actingAs($actor);
        $beforeActivation = $actor->is_activated;
        $url = route('table.bulk-change.save');
        $this->postJson($url, ['class' => UserTable::class, 'key' => 'status', 'value' => UserStatusEnum::DEACTIVATED, 'ids' => [(string) $actor->id]])->assertForbidden();
        $this->postJson($url, ['class' => UserTable::class, 'key' => 'email', 'value' => 'hijacked@example.test', 'ids' => [(string) $super->id]])->assertForbidden();
        $this->postJson($url, ['class' => RoleTable::class, 'key' => 'name', 'value' => 'Hijacked role', 'ids' => [(string) $role->id]])->assertForbidden();
        $this->assertSame('super@example.test', $super->fresh()->email);
        $this->assertSame('Higher role', $role->fresh()->name);
        $this->assertSame($beforeActivation, $actor->fresh()->is_activated);
    }

    #[TestWith(['page'])]
    #[TestWith(['user'])]
    public function test_canceled_second_legacy_bulk_update_rolls_back_the_entire_batch(string $kind): void
    {
        if ($kind === 'page') {
            $first = PageFactory::new()->create(['name' => 'First original']);
            $second = PageFactory::new()->create(['name' => 'Second original']);
            $table = PageTable::class;
            $key = 'name';
            $value = 'Canceled rename';
        } else {
            $first = UserFactory::new()->create(['email' => 'first@example.test']);
            $second = UserFactory::new()->create(['email' => 'second@example.test']);
            $table = UserTable::class;
            $key = 'email';
            $value = 'canceled@example.test';
        }
        $before = [$first->{$key}, $second->{$key}];
        $attempts = 0;
        Event::listen('eloquent.updating: '.$first::class, function () use (&$attempts) {
            $attempts++;

            return $attempts === 2 ? false : null;
        });
        $this->postJson(route('table.bulk-change.save'), ['class' => $table, 'key' => $key, 'value' => $value, 'ids' => [(string) $first->getKey(), (string) $second->getKey()]])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->assertSame(2, $attempts);
        $this->assertSame($before, [$first->fresh()->{$key}, $second->fresh()->{$key}]);
    }

    public function test_legacy_media_folder_endpoints_do_not_expose_another_owners_folder(): void
    {
        $actor = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $other = UserFactory::new()->create();
        $owned = MediaFolder::query()->create(['name' => 'Owned endpoint folder', 'slug' => 'owned-endpoint', 'parent_id' => 0, 'user_id' => $actor->id]);
        $foreign = MediaFolder::query()->create(['name' => 'Private endpoint folder', 'slug' => 'private-endpoint', 'parent_id' => 0, 'user_id' => $other->id]);
        setting()->set('user_can_only_view_own_media', true)->save();
        $this->actingAs($actor)->getJson(route('media.folder-picker'))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $owned->id);
        $this->getJson(route('media.breadcrumbs', ['folder_id' => $owned->id]))->assertOk()->assertJsonFragment(['name' => $owned->name]);
        $this->getJson(route('media.breadcrumbs', ['folder_id' => $foreign->id]))->assertForbidden()->assertDontSee('Private endpoint folder');
    }

    public function test_notification_count_is_permission_scoped_and_legacy_preferences_change_only_current_user(): void
    {
        AdminNotification::query()->create(['title' => 'Visible notice']);
        AdminNotification::query()->create(['title' => 'Restricted notice', 'permission' => 'roles.edit']);
        $actor = UserFactory::new()->create(['permissions' => []]);
        $this->actingAs($actor)->getJson(route('notifications.count-unread'))->assertOk()->assertJsonPath('data', 1);
        $other = UserFactory::new()->create();
        $this->from(route('dashboard.index'))->get(route('toggle-theme-mode', ['theme' => 'dark']))->assertRedirect(route('dashboard.index'));
        $this->assertSame('dark', UserMeta::getMeta('theme_mode', null, $actor->id));
        $this->assertNull(UserMeta::getMeta('theme_mode', null, $other->id));
        $widget = DashboardWidget::query()->create(['name' => 'endpoint-widget']);
        $this->getJson(route('dashboard.hide_widget', ['name' => $widget->name]))->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('dashboard_widget_settings', ['user_id' => $actor->id, 'widget_id' => $widget->id, 'status' => 0]);
        $this->assertDatabaseMissing('dashboard_widget_settings', ['user_id' => $other->id, 'widget_id' => $widget->id]);
    }

    public function test_email_iframe_renders_template_without_mutating_stored_body(): void
    {
        $this->get(route('settings.email.template.iframe', ['core', 'acl', 'password-reminder']))->assertOk()->assertSee('reset_link');
    }

    private function languageFixtures(): array
    {
        app(PluginService::class)->activate('language');
        $en = Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_rtl' => false, 'lang_order' => 1, 'lang_is_default' => true]);
        $vi = Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_is_rtl' => false, 'lang_order' => 2, 'lang_is_default' => false]);

        return [$en, $vi];
    }

    public function test_language_lookup_returns_selected_record_and_get_cannot_change_default_without_csrf(): void
    {
        [$en, $vi] = $this->languageFixtures();
        $this->getJson(route('languages.get', ['lang_id' => $vi->id ?? $vi->getKey()]))->assertOk()->assertJsonPath('data.lang_name', 'Vietnamese');
        $this->app->bind(PreventRequestForgery::class, EndpointEnforcedCsrf::class);
        $response = $this->getJson(route('languages.set.default', ['lang_id' => $vi->getKey()]));
        $this->assertTrue($en->fresh()->lang_is_default, 'GET changed the default language without a CSRF token.');
        $this->assertFalse($vi->fresh()->lang_is_default);
        $response->assertMethodNotAllowed();
    }

    public function test_demo_mode_cannot_change_default_language_via_legacy_get(): void
    {
        [$en, $vi] = $this->languageFixtures();
        config(['core.base.general.demo_mode_enabled' => true]);
        $response = $this->getJson(route('languages.set.default', ['lang_id' => $vi->getKey()]));
        $this->assertTrue($en->fresh()->lang_is_default, 'GET bypassed the demo-mode mutation guard.');
        $this->assertFalse($vi->fresh()->lang_is_default);
        $response->assertMethodNotAllowed();
    }

    public function test_post_default_language_requires_real_csrf_and_demo_guard_and_writes_once(): void
    {
        [$en, $vi] = $this->languageFixtures();
        $this->app->bind(PreventRequestForgery::class, EndpointEnforcedCsrf::class);
        $this->withSession(['_token' => 'endpoint-csrf-token']);
        $url = route('languages.set.default.update');
        $payload = ['lang_id' => $vi->getKey()];
        $updates = 0;
        Event::listen(UpdatedContentEvent::class, function ($event) use (&$updates): void {
            if ($event->data instanceof Language) {
                $updates++;
            }
        });
        $this->postJson($url, $payload)->assertStatus(419);
        $this->postJson($url, [...$payload, '_token' => 'wrong'])->assertStatus(419);
        $this->assertTrue($en->fresh()->lang_is_default);
        $this->assertSame(0, $updates);
        config(['core.base.general.demo_mode_enabled' => true]);
        $this->postJson($url, [...$payload, '_token' => 'endpoint-csrf-token'])->assertForbidden();
        $this->assertTrue($en->fresh()->lang_is_default);
        $this->assertSame(0, $updates);
        config(['core.base.general.demo_mode_enabled' => false]);
        $this->postJson($url, [...$payload, '_token' => 'endpoint-csrf-token'])->assertOk()->assertJsonPath('error', false);
        $this->assertFalse($en->fresh()->lang_is_default);
        $this->assertTrue($vi->fresh()->lang_is_default);
        $this->assertSame(1, $updates);
        config(['core.base.admin.legacy_mode' => true]);
        $this->get(route('languages.index'), ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))])
            ->assertOk()->assertJsonPath('component', 'Languages/Index');
    }

    public function test_locale_zip_download_reads_only_isolated_language_and_storage_paths(): void
    {
        app(PluginService::class)->activate('translation');
        $originalLang = lang_path();
        $originalStorage = storage_path();
        $temporary = $originalStorage.'/framework/testing/endpoints-'.uniqid();
        File::ensureDirectoryExists($temporary.'/lang/vi');
        File::put($temporary.'/lang/vi/messages.php', '<?php return ["endpoint" => "Fixture translation"];');
        $this->app->useLangPath($temporary.'/lang');
        $this->app->useStoragePath($temporary.'/storage');
        try {
            $response = $this->get(route('translations.locales.download', ['locale' => 'vi']))->assertDownload('locale-vi.zip');
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
            $this->assertStringContainsString('Fixture translation', $zip->getFromName('vi/messages.php'));
            $zip->close();
            $this->getJson(route('translations.locales.download', ['locale' => 'unknown']))->assertNotFound();
        } finally {
            $this->app->useLangPath($originalLang);
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($temporary);
        }
    }

    public function test_system_size_endpoint_calculates_only_a_temporary_application_fixture(): void
    {
        $originalBase = base_path();
        $temporary = storage_path('framework/testing/endpoint-size-'.uniqid());
        File::ensureDirectoryExists($temporary);
        File::put($temporary.'/fixture.txt', 'abc');
        $this->app->usePublicPath(public_path());
        $this->app->setBasePath($temporary);
        try {
            $this->getJson(route('system.info.get-addition-data'))->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.appSize', BaseHelper::humanFilesize(3));
        } finally {
            $this->app->setBasePath($originalBase);
            File::deleteDirectory($temporary);
        }
    }
}

class EndpointEnforcedCsrf extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

class EndpointLegacyBulkTable extends PageTable
{
    public function getAllBulkChanges(): array
    {
        return ['legacy' => ['title' => 'Legacy field', 'type' => 'text', 'validate' => 'required|string']];
    }
}

class EndpointNoEditBulkTable extends PageTable
{
    public function setup(): void
    {
        parent::setup();
        $this->removeAction('edit');
    }
}

class EndpointCanceledBulkTable extends PageTable
{
    public function setup(): void
    {
        parent::setup();
        $this->onSavingBulkChangeItem(fn (): bool => false);
    }
}
