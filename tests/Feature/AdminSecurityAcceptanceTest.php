<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Kern\ACL\Events\RoleAssignmentEvent;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\MetaBox;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminRenderer;
use Kern\Base\Supports\AdminSessionContext;
use Kern\Blog\Models\Post;
use Kern\Contact\Models\Contact;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Member\Models\Member;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Facades\Theme;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminSecurityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private ?string $recoveryLangPath = null;

    private ?string $originalRecoveryLangPath = null;

    protected function tearDown(): void
    {
        if ($this->recoveryLangPath !== null) {
            $this->app->useLangPath($this->originalRecoveryLangPath);
            File::deleteDirectory($this->recoveryLangPath);
        }
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    #[TestWith(['users.edit', false])]
    #[TestWith(['roles.edit', true])]
    public function test_assignment_endpoint_and_profile_schema_agree_for_independent_edit_permissions(string $permission, bool $canAssign): void
    {
        $actor = UserFactory::new()->create(['permissions' => [$permission => true, 'users.index' => true]]);
        $other = UserFactory::new()->create();
        $protected = UserFactory::new()->superUser()->create();
        $role = Role::query()->create(['name' => 'Unprivileged assignment', 'permissions' => [], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->actingAs($actor);
        $this->get(route('users.profile.view', $actor), $this->headers())->assertOk()->assertJsonPath('props.canAssignRole', $canAssign);
        $this->get(route('users.index', ['search' => $other->username]), $this->headers())->assertOk()
            ->assertJsonPath('props.items.data.0.can_assign_role', $canAssign)
            ->assertJsonPath('props.roleOptions', $canAssign ? [['value' => (string) $role->id, 'label' => $role->name]] : [])
            ->assertJsonPath('props.assignRoleUrl', $canAssign ? route('roles.assign') : null);
        $this->get(route('users.index', ['search' => $protected->username]), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.can_assign_role', false);
        $response = $this->post(route('roles.assign'), ['pk' => $other->id, 'value' => $role->id], $this->headers());
        if ($canAssign) {
            $response->assertRedirect();
            $this->assertTrue($other->roles()->whereKey($role->id)->exists());
            $this->get(route('users.profile.view', $other), $this->headers())->assertForbidden();
            $this->post(route('roles.assign'), ['pk' => $protected->id, 'value' => $role->id], $this->headers())->assertForbidden();
            $this->assertSame(0, $protected->roles()->count());
        } else {
            $response->assertForbidden();
            $this->assertSame(0, $other->roles()->count());
            $this->get(route('users.profile.view', $other), $this->headers())->assertOk()->assertJsonPath('props.canAssignRole', false);
        }
    }

    public function test_assigned_role_applies_to_real_allowed_and_forbidden_routes(): void
    {
        $administrator = UserFactory::new()->superUser()->create();
        $member = UserFactory::new()->create(['permissions' => []]);
        $role = Role::query()->create(['name' => 'Page reader', 'permissions' => ['pages.index' => true], 'created_by' => $administrator->id, 'updated_by' => $administrator->id]);
        $this->actingAs($administrator)->post(route('roles.assign'), ['pk' => $member->id, 'value' => $role->id], $this->headers())->assertRedirect();
        $this->actingAs($member->fresh());
        $this->get(route('pages.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Pages/Index')->assertJsonPath('props.permissions.create', false);
        $this->get(route('users.index'), $this->headers())->assertForbidden();
        $this->post(route('pages.store'), ['name' => 'Reader cannot create', 'status' => 'published'], $this->headers())->assertForbidden();
        $this->assertDatabaseMissing('pages', ['name' => 'Reader cannot create']);
    }

    public function test_inline_self_assignment_reports_success_even_when_the_new_role_removes_user_list_access(): void
    {
        $actor = UserFactory::new()->create(['permissions' => []]);
        $current = Role::query()->create(['name' => 'Role administrator', 'permissions' => ['roles.edit' => true, 'users.index' => true], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $lower = Role::query()->create(['name' => 'Unprivileged role', 'permissions' => [], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $actor->roles()->attach($current);
        event(new RoleAssignmentEvent($current, $actor));
        $this->actingAs($actor->fresh())->get(route('users.index'), $this->headers())->assertOk();
        $assignments = 0;
        Event::listen(RoleAssignmentEvent::class, function () use (&$assignments): void {
            $assignments++;
        });
        $this->from(route('users.index'))->post(route('roles.assign'), ['pk' => $actor->id, 'value' => $lower->id], [...$this->headers(), 'Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('error', false);
        $this->assertSame([$lower->id], $actor->roles()->pluck('roles.id')->all());
        $this->assertSame(1, $assignments);
        $this->actingAs($actor->fresh())->get(route('users.index'), $this->headers())->assertForbidden();
    }

    public function test_retired_legacy_flag_does_not_disable_authorized_json_role_assignment(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $member = UserFactory::new()->create(['permissions' => ['pages.index' => true]]);
        $role = Role::query()->create(['name' => 'Empty role', 'permissions' => [], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $assignments = 0;
        Event::listen(RoleAssignmentEvent::class, function () use (&$assignments): void {
            $assignments++;
        });
        config(['core.base.admin.legacy_mode' => true]);
        $this->actingAs($actor)->from(route('users.index'))
            ->post(route('roles.assign'), ['pk' => $member->id, 'value' => $role->id], [...$this->headers(), 'Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('error', false);
        $this->assertSame(1, $assignments);
        $this->assertSame($role->id, $member->fresh()->roles->sole()->id);
        $this->assertFalse($member->fresh()->hasPermission('pages.index'));
    }

    public function test_expired_inertia_mutation_returns_to_edit_with_an_error_without_writing_or_reporting_success(): void
    {
        $page = PageFactory::new()->create(['name' => 'Persisted before expiry']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $editUrl = route('pages.edit', $page);
        $this->withSession(['_token' => 'valid-expiry-token']);

        $this->from($editUrl)->post(route('pages.update', $page), ['name' => 'Unsaved draft', 'status' => 'published', '_token' => 'expired-token'], $this->headers())
            ->assertRedirect($editUrl);
        $this->assertSame('Persisted before expiry', $page->fresh()->name);
        $this->get($editUrl, $this->headers())->assertOk()->assertJsonPath('component', 'Pages/Form')
            ->assertJsonPath('props.errors._request', 'Your session expired. Your changes have not been saved. Please try again.')
            ->assertJsonPath('props.flash.error', 'Your session expired. Your changes have not been saved. Please try again.')
            ->assertJsonPath('props.flash.success', null);
    }

    #[TestWith(['https://outside.example.test/admin/pages'])]
    #[TestWith(['http://localhost/'])]
    public function test_expired_inertia_mutation_never_redirects_to_an_untrusted_or_public_return_url(string $returnUrl): void
    {
        $page = PageFactory::new()->create(['name' => 'Persisted before expiry']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $this->withSession(['_token' => 'valid-expiry-token']);

        $this->from($returnUrl)->post(route('pages.update', $page), ['name' => 'Rejected draft', 'status' => 'published', '_token' => 'expired-token'], $this->headers())
            ->assertStatus(419)->assertHeaderMissing('Location');
        $this->assertSame('Persisted before expiry', $page->fresh()->name);
    }

    #[TestWith(['roles.create'])]
    #[TestWith(['pages.edit'])]
    public function test_expired_inertia_mutation_rejects_a_different_form_or_record_as_return_destination(string $returnRoute): void
    {
        $page = PageFactory::new()->create(['name' => 'Original target']);
        $other = PageFactory::new()->create(['name' => 'Other target']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $this->withSession(['_token' => 'valid-expiry-token']);

        $this->from(route($returnRoute, $returnRoute === 'pages.edit' ? $other : []))
            ->post(route('pages.update', $page), ['name' => 'Rejected draft', 'status' => 'published', '_token' => 'expired-token'], $this->headers())
            ->assertStatus(419)->assertHeaderMissing('Location');
        $this->assertSame('Original target', $page->fresh()->name);
        $this->assertSame('Other target', $other->fresh()->name);
    }

    #[TestWith(['vi', 'en_US'])]
    #[TestWith([null, 'vi'])]
    #[TestWith(['vi', null])]
    public function test_expired_inertia_mutation_does_not_switch_the_form_translation_scope(?string $returnLocale, ?string $submitLocale): void
    {
        foreach (['language', 'language-advanced'] as $plugin) {
            $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
        }
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_is_default' => false]);
        $page = PageFactory::new()->create(['name' => 'Original locale target']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $this->withSession(['_token' => 'valid-expiry-token']);

        $this->from(route('pages.edit', ['page' => $page, 'ref_lang' => $returnLocale]))
            ->post(route('pages.update', ['page' => $page, 'ref_lang' => $submitLocale]), ['name' => 'Wrong locale draft', 'status' => 'published', '_token' => 'expired-token'], $this->headers())
            ->assertStatus(419)->assertHeaderMissing('Location');
        $this->assertSame('Original locale target', $page->fresh()->name);
    }

    public function test_expired_default_language_form_recovers_when_its_submit_url_explicitly_adds_the_default_locale(): void
    {
        foreach (['language', 'language-advanced'] as $plugin) {
            $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
        }
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_is_default' => false]);
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        $page = PageFactory::new()->create(['name' => 'Default language original']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $editUrl = route('pages.edit', $page);
        $opened = $this->get($editUrl, $this->headers())->assertOk()->assertJsonPath('props.language', 'en_US');
        $submitUrl = $opened->json('props.urls.submit');
        $this->assertSame(route('pages.update', ['page' => $page, 'ref_lang' => 'en_US']), $submitUrl);
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $this->withSession(['_token' => 'valid-default-locale-token']);

        $this->from($editUrl)->post($submitUrl, ['name' => 'Unsaved default draft', 'status' => 'published', '_token' => 'expired-token'], $this->headers())->assertRedirect($editUrl);
        $this->assertSame('Default language original', $page->fresh()->name);
        $this->get($editUrl, $this->headers())->assertOk()->assertJsonPath('component', 'Pages/Form')->assertJsonPath('props.language', 'en_US')
            ->assertJsonPath('props.errors._request', 'Your session expired. Your changes have not been saved. Please try again.')
            ->assertJsonPath('props.flash.success', null);
    }

    #[TestWith(['contacts.reply', 'contacts.edit', 'contact', 'post'])]
    #[TestWith(['users.update-profile', 'users.profile.view', 'user', 'put'])]
    #[TestWith(['users.change-password', 'users.profile.view', 'user', 'put'])]
    #[TestWith(['users.update-preferences', 'users.profile.view', 'user', 'put'])]
    #[TestWith(['users.profile.image', 'users.profile.view', 'user', 'post'])]
    #[TestWith(['users.profile.image.destroy', 'users.profile.view', 'user', 'post'])]
    #[TestWith(['users.make-super', 'users.profile.view', 'user', 'post'])]
    #[TestWith(['users.remove-super', 'users.profile.view', 'user', 'delete'])]
    #[TestWith(['roles.assign', 'users.profile.view', 'assignment', 'post'])]
    #[TestWith(['roles.duplicate', 'roles.edit', 'role', 'post'])]
    #[TestWith(['languages.store', 'languages.index', 'language', 'post'])]
    #[TestWith(['languages.edit', 'languages.index', 'language', 'post'])]
    #[TestWith(['languages.settings', 'languages.index', 'language', 'post'])]
    #[TestWith(['languages.set.default.update', 'languages.index', 'language', 'post'])]
    #[TestWith(['translations.group.edit', 'translations.index', 'translation', 'post'])]
    #[TestWith(['translations.theme-translations.post', 'translations.theme-translations', 'theme-translation', 'post'])]
    #[TestWith(['translations.theme-translations.re-import', 'translations.theme-translations', 'theme-translation', 'post'])]
    #[TestWith(['translations.react.import', 'translations.index', 'translation-import', 'post'])]
    #[TestWith(['translations.react.import', 'translations.theme-translations', 'theme-import', 'post'])]
    #[TestWith(['translations.locales.delete', 'translations.locales', 'locale', 'delete'])]
    #[TestWith(['settings.email.template.restore', 'settings.email.template.edit', 'email', 'post'])]
    #[TestWith(['custom-fields.import', 'custom-fields.index', 'fields', 'post'])]
    #[TestWith(['member.verify-email', 'member.edit', 'member', 'post'])]
    #[TestWith(['theme.active', 'theme.index', 'theme', 'post'])]
    #[TestWith(['theme.remove', 'theme.index', 'theme', 'post'])]
    #[TestWith(['contacts.reply', 'contacts.edit', 'contact', 'post', 'wrong-record'])]
    #[TestWith(['users.update-profile', 'users.profile.view', 'user', 'put', 'wrong-record'])]
    #[TestWith(['users.change-password', 'users.profile.view', 'user', 'put', 'wrong-record'])]
    #[TestWith(['roles.assign', 'users.profile.view', 'assignment', 'post', 'wrong-record'])]
    #[TestWith(['roles.assign', 'users.profile.view', 'assignment', 'post', 'wrong-route'])]
    #[TestWith(['roles.assign', 'users.profile.view', 'assignment', 'post', 'malformed-pk'])]
    #[TestWith(['roles.duplicate', 'roles.edit', 'role', 'post', 'wrong-record'])]
    #[TestWith(['member.verify-email', 'member.edit', 'member', 'post', 'wrong-record'])]
    #[TestWith(['settings.email.template.restore', 'settings.email.template.edit', 'email', 'post', 'wrong-record'])]
    #[TestWith(['translations.group.edit', 'translations.index', 'translation', 'post', 'wrong-locale'])]
    #[TestWith(['translations.theme-translations.post', 'translations.theme-translations', 'theme-translation', 'post', 'wrong-locale'])]
    #[TestWith(['translations.react.import', 'translations.index', 'translation-import', 'post', 'wrong-theme'])]
    #[TestWith(['contacts.reply', 'contacts.edit', 'contact', 'post', 'guest'])]
    #[TestWith(['translations.group.edit', 'translations.index', 'translation', 'post', null, 'vi'])]
    #[TestWith(['translations.theme-translations.post', 'translations.theme-translations', 'theme-translation', 'post', null, 'vi'])]
    #[TestWith(['translations.theme-translations.re-import', 'translations.theme-translations', 'theme-translation', 'post', null, 'vi'])]
    #[TestWith(['translations.react.import', 'translations.index', 'translation-import', 'post', null, 'vi'])]
    #[TestWith(['translations.react.import', 'translations.theme-translations', 'theme-import', 'post', null, 'vi'])]
    public function test_expired_custom_inertia_actions_recover_only_their_matching_form_and_scope(string $source, string $destination, string $kind, string $method, ?string $mismatch = null, string $locale = 'en'): void
    {
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
        $user = UserFactory::new()->create();
        $contact = Contact::query()->create(['name' => 'Recovery sender', 'email' => 'sender@example.test', 'content' => 'Original contact']);
        $role = Role::query()->create(['name' => 'Recovery role', 'permissions' => [], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        $member = Member::query()->create(['first_name' => 'Recovery', 'last_name' => 'Member', 'email' => 'recovery@example.test', 'password' => 'local-fixture-password']);
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_is_default' => false]);
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        Theme::setThemeName('ripple');
        $sourceParams = match ($kind) {
            'contact' => ['contact' => $contact], 'user' => ['user' => $user], 'role' => ['role' => $role],
            'member' => ['id' => $member->id], 'locale' => ['locale' => 'vi'],
            'email' => ['type' => 'core', 'module' => 'base', 'template' => 'header'], default => [],
        };
        $returnParams = match ($kind) {
            'assignment' => ['user' => $user], 'member' => ['member' => $member],
            'translation', 'theme-translation', 'translation-import', 'theme-import' => [],
            'locale' => [], default => $sourceParams,
        };
        if (in_array($kind, ['translation', 'theme-translation', 'translation-import', 'theme-import'], true)) {
            $this->originalRecoveryLangPath = lang_path();
            $this->recoveryLangPath = sys_get_temp_dir().'/kern-recovery-languages-'.bin2hex(random_bytes(8));
            foreach (['en', 'vi'] as $installedLocale) {
                File::ensureDirectoryExists($this->recoveryLangPath.'/'.$installedLocale);
                File::put($this->recoveryLangPath.'/'.$installedLocale.'/messages.php', '<?php return ["fixture" => "Fixture"];');
            }
            $this->app->useLangPath($this->recoveryLangPath);
            if ($locale !== 'en') {
                $returnParams['ref_lang'] = $locale;
            }
        }
        $payload = ['_token' => 'expired-token', 'message' => '<p>Retain draft</p>', 'pk' => $kind === 'assignment' ? $user->id : $locale, 'name' => $locale.'|fixture', 'ref_lang' => in_array($kind, ['translation-import', 'theme-import'], true) ? $locale : null, 'theme' => $kind === 'theme-import', 'lang_id' => 2];
        if ($mismatch === 'wrong-record') {
            $returnParams = match ($kind) {
                'contact' => ['contact' => $contact->id + 100], 'user', 'assignment' => ['user' => $user->id + 100],
                'role' => ['role' => $role->id + 100], 'member' => ['member' => $member->id + 100],
                'email' => [...$returnParams, 'template' => 'footer'], default => $returnParams,
            };
        } elseif ($mismatch === 'wrong-route') {
            $destination = 'roles.edit';
            $returnParams = ['role' => $role];
        } elseif ($mismatch === 'malformed-pk') {
            $payload['pk'] = [];
        } elseif ($mismatch === 'wrong-locale') {
            $returnParams['ref_lang'] = 'vi';
        } elseif ($mismatch === 'wrong-theme') {
            $destination = 'translations.theme-translations';
        } elseif ($mismatch === 'guest') {
            Auth::logout();
            $payload['_token'] = 'valid-custom-recovery-token';
        }
        $tables = ['roles', 'role_users', 'contacts', 'contact_replies', 'members', 'languages', 'settings'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy(Schema::getColumnListing($table)[0])->get()->toJson();
        }
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $this->withSession(['_token' => 'valid-custom-recovery-token']);
        $returnUrl = route($destination, $returnParams);

        $response = $this->from($returnUrl)->call(strtoupper($method), route($source, $sourceParams), $payload, [], [], $this->transformHeadersToServerVars($this->headers()));
        if ($mismatch === 'guest') {
            $response->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
        } elseif ($mismatch !== null) {
            $response->assertStatus(419)->assertHeaderMissing('Location');
        } else {
            $response->assertRedirect($returnUrl);
            $this->get($returnUrl, $this->headers())->assertOk()->assertJsonPath('props.errors._request', 'Your session expired. Your changes have not been saved. Please try again.')->assertJsonPath('props.flash.success', null);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy(Schema::getColumnListing($table)[0])->get()->toJson(), $table);
        }
        $this->assertSame(0, $user->fresh()->roles()->count());
        $this->assertFalse($user->fresh()->isSuperUser());
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_real_csrf_rejects_missing_and_invalid_tokens_without_mutation_and_valid_token_updates_once(): void
    {
        $page = PageFactory::new()->create(['name' => 'Persisted original', 'content' => '<p>Original content</p>']);
        MetaBox::saveMetaBoxData($page, 'seo_meta', ['seo_title' => 'Original SEO']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $updates = 0;
        Event::listen(UpdatedContentEvent::class, function ($event) use (&$updates): void {
            if ($event->data instanceof Page) {
                $updates++;
            }
        });
        $this->withSession(['_token' => 'acceptance-session-csrf-token']);
        $payload = ['name' => 'Attempted draft title', 'status' => 'published', 'content' => '<p>Attempted draft content</p>'];
        $this->post(route('pages.update', $page), $payload, $this->headers())->assertStatus(419)->assertJsonPath('props.status', 419);
        $this->post(route('pages.update', $page), [...$payload, '_token' => 'wrong-token'], $this->headers())->assertStatus(419);
        $this->assertSame('Persisted original', $page->fresh()->name);
        $this->assertSame('<p>Original content</p>', $page->fresh()->content);
        $this->assertSame('Original SEO', MetaBox::getMetaData($page->fresh(), 'seo_meta', true)['seo_title']);
        $this->assertSame(0, $updates);
        $this->post(route('pages.update', $page), [...$payload, '_token' => 'acceptance-session-csrf-token'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Attempted draft title', $page->fresh()->name);
        $this->assertSame('<p>Attempted draft content</p>', $page->fresh()->content);
        $this->assertSame('Original SEO', MetaBox::getMetaData($page->fresh(), 'seo_meta', true)['seo_title']);
        $this->assertSame(1, $updates);
        $this->assertSame(1, Page::query()->count());
    }

    #[TestWith(['pages', 'update'])]
    #[TestWith(['pages', 'delete'])]
    #[TestWith(['posts', 'update'])]
    #[TestWith(['posts', 'delete'])]
    public function test_mid_batch_failure_rolls_back_selected_rows_and_metadata_without_success(string $module, string $action): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        if ($module === 'posts') {
            $this->assertFalse(app(PluginService::class)->activate('blog')['error']);
            $models = collect(['First', 'Second', 'Not selected'])->map(fn (string $name) => Post::query()->create(['name' => $name, 'status' => 'published', 'content' => '<p>'.$name.'</p>', 'author_id' => auth()->id(), 'author_type' => User::class]));
        } else {
            $models = collect(['First', 'Second', 'Not selected'])->map(fn (string $name) => PageFactory::new()->create(['name' => $name, 'content' => '<p>'.$name.'</p>']));
        }
        foreach ($models as $model) {
            MetaBox::saveMetaBoxData($model, 'seo_meta', ['seo_title' => 'Keep '.$model->name]);
        }
        $class = $models[0]::class;
        $attempts = 0;
        $event = $action === 'delete' ? 'deleting' : 'updating';
        Event::listen('eloquent.'.$event.': '.$class, function () use (&$attempts): void {
            $attempts++;
            if ($attempts === 2) {
                throw new RuntimeException('Acceptance rejects the second record');
            }
        });
        $payload = ['ids' => [$models[0]->id, $models[1]->id], 'action' => $action === 'delete' ? 'delete' : ($module === 'pages' ? 'update' : 'change')];
        if ($action === 'update') {
            $payload += ['field' => 'name', 'value' => 'Unexpected partial rename'];
        }
        $response = $this->post(route($module.'.bulk'), $payload, $this->headers());
        if ($module === 'posts' && $action === 'delete') {
            $response->assertRedirect()->assertSessionHasErrors('ids');
        } else {
            $response->assertStatus(500);
        }
        $response->assertSessionMissing('success_msg');
        $this->assertSame(2, $attempts);
        foreach ($models as $index => $model) {
            $this->assertModelExists($model);
            $this->assertSame(['First', 'Second', 'Not selected'][$index], $model->fresh()->name);
            $this->assertSame('<p>'.$model->name.'</p>', $model->fresh()->content);
            $this->assertSame('Keep '.$model->name, MetaBox::getMetaData($model->fresh(), 'seo_meta', true)['seo_title']);
        }
    }

    public function test_restoring_manifest_and_chunks_restores_an_actual_inertia_get_response(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $publicPath = $this->app->publicPath();
        $fixture = sys_get_temp_dir().'/kern-security-assets-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($fixture.'/build/admin/assets');
        $manifest = json_encode(['resources/js/admin.tsx' => ['file' => 'assets/admin.js', 'isEntry' => true, 'css' => ['assets/admin.css']]]);
        file_put_contents($fixture.'/build/admin/manifest.json', $manifest);
        file_put_contents($fixture.'/build/admin/assets/admin.js', 'export {};');
        file_put_contents($fixture.'/build/admin/assets/admin.css', 'body {}');
        $this->app->usePublicPath($fixture);
        Route::middleware(['web', HandleAdminInertiaRequests::class.':pages'])->get('/admin/security-assets', fn () => app(AdminRenderer::class)->usesInertia('pages') ? Inertia::render('Pages/Index', ['acceptance_phase' => 'react']) : response('<h1>Blade fallback</h1>'))->name('pages.security-assets');
        Route::getRoutes()->refreshNameLookups();
        try {
            $this->get('/admin/security-assets', $this->headers())->assertOk()->assertJsonPath('component', 'Pages/Index');
            foreach (['build/admin/manifest.json' => $manifest, 'build/admin/assets/admin.css' => 'body {}'] as $path => $content) {
                unlink($fixture.'/'.$path);
                $this->get('/admin/security-assets')->assertStatus(503)->assertSee('React admin is unavailable');
                $this->get('/admin/security-assets', $this->headers())->assertStatus(503)->assertJsonPath('error', true)->assertSee('npm run build:admin');
                file_put_contents($fixture.'/'.$path, $content);
                $this->get('/admin/security-assets', $this->headers())->assertOk()->assertJsonPath('component', 'Pages/Index')->assertJsonPath('props.acceptance_phase', 'react');
            }
        } finally {
            $this->app->usePublicPath($publicPath);
            File::deleteDirectory($fixture);
        }
    }

    #[TestWith(['authentication', false])]
    #[TestWith(['csrf', false])]
    #[TestWith(['authentication', true])]
    #[TestWith(['csrf', true])]
    public function test_original_actor_context_keeps_expired_mutations_unsaved_until_the_same_actor_manually_reauthenticates(string $expiry, bool $ajax): void
    {
        $page = PageFactory::new()->create(['name' => 'Original persisted page']);
        $actor = UserFactory::new()->superUser()->create();
        $other = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        $this->app->bind(PreventRequestForgery::class, EnforcedAcceptanceCsrf::class);
        $edit = route('pages.edit', $page);
        $context = $this->get($edit, $this->headers())->assertOk()->json('props.admin.session.context');
        $headers = $ajax ? ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest', AdminSessionContext::HEADER => $context] : [...$this->headers(), AdminSessionContext::HEADER => $context];
        $this->withSession(['_token' => 'valid-context-csrf']);
        if ($expiry === 'authentication') {
            $this->app->instance('acceptance.expire-original-session', true);
            Route::getRoutes()->getByName('pages.update')->middleware(ExpireOriginalAcceptanceSession::class);
        } else {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }
        $payload = ['name' => 'Retained draft', 'status' => 'published', '_token' => 'valid-context-csrf'];
        $response = $this->from($edit)->post(route('pages.update', $page), $payload, $headers)->assertUnauthorized()->assertHeader('X-Admin-Session-Recovery', '1')->assertJsonPath('code', 'admin_session_expired');
        $this->assertFalse($response->headers->has('X-Inertia-Location'));
        $this->assertFalse($response->headers->has('X-Inertia'));
        $this->assertSame('Original persisted page', $page->fresh()->name);
        $this->assertGuest();
        $this->actingAs($other);
        $this->getJson(route('admin.session-status'), [AdminSessionContext::HEADER => $context])->assertOk()->assertExactJson(['matches' => false])->assertHeader('Cache-Control', 'no-store, private');
        $this->withSession(['_token' => 'new-other-csrf']);
        $this->from($edit)->post(route('pages.update', $page), [...$payload, '_token' => 'new-other-csrf'], $headers)->assertUnauthorized()->assertHeader('X-Admin-Session-Recovery', '1');
        $this->assertAuthenticatedAs($other);
        $this->assertSame('Original persisted page', $page->fresh()->name);
        $this->actingAs($actor);
        $this->getJson(route('admin.session-status'), [AdminSessionContext::HEADER => $context])->assertOk()->assertExactJson(['matches' => true]);
        $this->withSession(['_token' => 'new-original-csrf']);
        $writes = 0;
        Event::listen(UpdatedContentEvent::class, function () use (&$writes): void {
            $writes++;
        });
        $saved = $this->from($edit)->post(route('pages.update', $page), ['name' => 'Retained draft', 'status' => 'published'], [...$headers, 'X-CSRF-TOKEN' => 'new-original-csrf']);
        if ($ajax) {
            $saved->assertOk()->assertJsonPath('error', false);
        } else {
            $saved->assertRedirect();
        }
        $this->assertSame('Retained draft', $page->fresh()->name);
        $this->assertSame(1, $writes);
    }

    #[TestWith(['forged-context'])]
    #[TestWith(['forbidden'])]
    public function test_actor_fence_and_ordinary_access_denial_never_write_or_log_out_the_current_account(string $case): void
    {
        $page = PageFactory::new()->create(['name' => 'Protected persisted page']);
        $actor = UserFactory::new()->create(['permissions' => ['pages.index' => true]]);
        $this->actingAs($actor);
        $context = $case === 'forged-context' ? 'forged-original-actor' : AdminSessionContext::issue($actor);
        $this->from(route('pages.edit', $page))->post(route('pages.update', $page), ['name' => 'Forbidden draft', 'status' => 'published'], [...$this->headers(), AdminSessionContext::HEADER => $context])
            ->assertForbidden()->assertHeader('X-Admin-Draft-Forbidden', '1');
        $this->assertSame('Protected persisted page', $page->fresh()->name);
        $this->assertAuthenticatedAs($actor);
    }

    #[TestWith(['other-record'])]
    #[TestWith(['external'])]
    #[TestWith(['wrong-locale'])]
    #[TestWith(['unmarked'])]
    public function test_guest_recovery_does_not_relax_the_existing_return_scope_or_direct_guest_contract(string $case): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $page = PageFactory::new()->create(['name' => 'Never written']);
        $other = PageFactory::new()->create();
        $headers = $this->headers();
        if ($case !== 'unmarked') {
            $headers[AdminSessionContext::HEADER] = AdminSessionContext::issue($actor);
        }
        $return = match ($case) {
            'other-record' => route('pages.edit', $other),
            'external' => 'https://outside.example/admin/pages/edit/'.$page->id,
            'wrong-locale' => route('pages.edit', ['page' => $page, 'ref_lang' => 'vi']),
            default => route('pages.edit', $page),
        };
        $this->from($return)->post(route('pages.update', $page), ['name' => 'Guest draft'], $headers)->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
        $this->assertSame('Never written', $page->fresh()->name);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_server_failures_preserve_only_the_original_actor_matching_form_without_leaking_details(bool $wrongRecord): void
    {
        $page = PageFactory::new()->create(['name' => 'Persisted before server failure']);
        $other = PageFactory::new()->create();
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        $this->app->instance('acceptance.fail-original-draft', true);
        Route::getRoutes()->getByName('pages.update')->middleware(ExpireOriginalAcceptanceSession::class);
        $response = $this->from(route('pages.edit', $wrongRecord ? $other : $page))->post(route('pages.update', $page), ['name' => 'Unsaved server failure draft'], [...$this->headers(), AdminSessionContext::HEADER => AdminSessionContext::issue($actor)])->assertStatus(500);
        if ($wrongRecord) {
            $this->assertFalse($response->headers->has('X-Admin-Draft-Error'));
        } else {
            $response->assertHeader('X-Admin-Draft-Error', '1');
        }
        $response->assertJsonPath('component', 'Pages/Error')->assertJsonPath('props.message', 'The request could not be completed.');
        $this->assertStringNotContainsString('private database detail', $response->getContent());
        $this->assertSame('Persisted before server failure', $page->fresh()->name);
        $this->assertAuthenticatedAs($actor);
    }
}

class EnforcedAcceptanceCsrf extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

class ExpireOriginalAcceptanceSession
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (app()->bound('acceptance.fail-original-draft') && app('acceptance.fail-original-draft')) {
            throw new RuntimeException('private database detail');
        }
        if (app()->bound('acceptance.expire-original-session') && app('acceptance.expire-original-session')) {
            app()->instance('acceptance.expire-original-session', false);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
