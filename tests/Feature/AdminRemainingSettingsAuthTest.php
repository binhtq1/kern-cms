<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Kern\ACL\Forms\Auth\LoginForm;
use Kern\ACL\Notifications\ResetPasswordNotification;
use Kern\ACL\Services\ActivateUserService;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Blog\Forms\Settings\BlogSettingForm;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingSettingsAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.auth' => 'inertia', 'core.base.admin.modules.settings' => 'inertia']);
        $this->withoutVite();
        foreach (['blog', 'contact', 'member'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_demo_login_prefill_is_scoped_to_demo_login_only(): void
    {
        config(['core.base.general.demo_mode_enabled' => true, 'core.base.general.demo.account.username' => 'demo-admin', 'core.base.general.demo.account.password' => 'demo-only-password']);
        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.credentials.username', 'demo-admin')->assertJsonPath('props.credentials.password', 'demo-only-password');
        $this->get(route('access.password.request'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.credentials.username', '')->assertJsonPath('props.credentials.password', '');
        $this->get(route('access.password.reset', ['token' => 'test-token']), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.credentials.password', '');
        config(['core.base.general.demo_mode_enabled' => false]);
        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.credentials.username', '')->assertJsonPath('props.credentials.password', '');
    }

    public function test_authentication_shares_saved_login_background_and_branding(): void
    {
        setting()->set(['login_screen_backgrounds' => json_encode(['https://example.test/login-background.jpg']), 'admin_logo' => 'https://example.test/logo.png', 'admin_logo_max_height' => 48, 'admin_appearance_locale_direction' => 'rtl'])->save();

        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Auth/Form')
            ->assertJsonPath('props.admin.appearance.loginBackground', 'https://example.test/login-background.jpg')
            ->assertJsonPath('props.admin.appearance.logo', 'https://example.test/logo.png')
            ->assertJsonPath('props.admin.appearance.logoHeight', 48)
            ->assertJsonPath('props.admin.appearance.direction', 'rtl')->assertJsonPath('props.admin.urls.profile', null);
    }

    public function test_remaining_settings_support_full_html_inertia_and_ignore_blade_module_flag(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        foreach (['blog.settings', 'contact.settings', 'member.settings', 'slug.settings'] as $route) {
            $this->get(route($route))->assertOk()->assertViewIs('core/base::admin.app');
            $this->get(route($route), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form')->assertJsonStructure(['props' => ['fields', 'submit']]);
        }
        config(['core.base.admin.modules.settings' => 'blade']);
        foreach (['blog.settings', 'contact.settings', 'member.settings', 'slug.settings'] as $route) {
            $this->get(route($route), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
        }
    }

    public function test_untouched_settings_checkbox_defaults_submit_without_validation_errors(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        foreach (['blog.settings', 'member.settings'] as $route) {
            $response = $this->get(route($route), $this->inertiaHeaders())->assertOk();
            $fields = collect($response->json('props.fields'));
            foreach ($fields->where('type', 'checkbox') as $field) {
                $this->assertIsInt($field['value']);
            }
            $data = $fields->reject(fn (array $field): bool => in_array($field['type'], ['notice', 'json-upload'], true))->mapWithKeys(fn (array $field): array => [$field['name'] => $field['value']])->all();
            $this->put(route($route.'.update'), $data, $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        }
    }

    public function test_remaining_settings_preserve_updates_validation_and_permissions(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->put(route('blog.settings.update'), ['blog_post_schema_enabled' => '1', 'blog_post_schema_type' => 'Article'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Article', setting('blog_post_schema_type'));
        $this->put(route('member.settings.update'), ['member_enabled_login' => '1', 'member_enabled_registration' => '0', 'verify_account_email' => '1'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('0', setting('member_enabled_registration'));
        $this->put(route('contact.settings.update'), ['receiver_emails' => '[{"value":"admin@example.test"}]', 'blacklist_keywords' => '[{"value":"spam"}]'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStringContainsString('admin@example.test', setting('receiver_emails'));
        $this->put(route('contact.settings.update'), ['receiver_emails' => '[{"value":"invalid"}]'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('receiver_emails_for_validation.0');
        $response = $this->get(route('slug.settings'), $this->inertiaHeaders())->assertOk();
        $fields = collect($response->json('props.fields'));
        $hidden = $fields->firstWhere('type', 'hidden');
        $this->assertNotNull($hidden);
        $prefix = str_replace('-model-key', '', $hidden['name']);
        $this->put(route('slug.settings.update'), [$prefix => 'articles', $hidden['name'] => $hidden['value']], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('articles', setting($prefix));
        $this->put(route('slug.settings.update'), [$prefix => 'invalid?prefix', $hidden['name'] => $hidden['value']], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors($prefix);
        $this->actingAs(UserFactory::new()->create());
        foreach (['blog.settings', 'contact.settings', 'member.settings', 'slug.settings'] as $route) {
            $this->get(route($route), $this->inertiaHeaders())->assertForbidden();
            $this->put(route($route.'.update'), [], $this->inertiaHeaders())->assertForbidden();
        }
    }

    public function test_unknown_settings_fields_and_auth_html_changes_are_blocked_safely(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        BlogSettingForm::beforeRendering(fn (BlogSettingForm $form) => $form->add('extension', HtmlField::class, ['html' => '<p>Extra setting</p>']));
        $this->get(route('blog.settings'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        Auth::logout();
        LoginForm::beforeRendering(fn (LoginForm $form) => $form->modify('password', HtmlField::class, ['html' => '<p>Custom challenge</p>']));
        $this->get(route('access.login'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_guest_auth_pages_have_no_protected_navigation_or_user_and_ignore_auth_module_flag(): void
    {
        $routes = [route('access.login'), route('access.password.request'), route('access.password.reset', ['token' => 'test-reset-token', 'email' => 'admin@example.test'])];
        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertViewIs('core/base::admin.app');
            $this->get($url, $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Auth/Form')->assertJsonPath('props.admin.navigation', [])->assertJsonPath('props.admin.user', null)->assertDontSee('password123');
        }
        config(['core.base.admin.modules.auth' => 'blade']);
        foreach ($routes as $url) {
            $this->get($url, $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Auth/Form');
        }
    }

    public function test_login_accepts_username_and_email_preserves_remember_and_regenerates_session(): void
    {
        $user = UserFactory::new()->superUser()->create(['username' => 'admin-user', 'email' => 'admin@example.test']);
        app(ActivateUserService::class)->activate($user);
        foreach ([$user->username, $user->email] as $identifier) {
            $this->get(route('access.login'));
            $sessionId = session()->getId();
            $this->post(route('access.login.post'), ['username' => $identifier, 'password' => 'password123', 'remember' => true], $this->inertiaHeaders())->assertRedirect(route('dashboard.index'))->assertCookie(Auth::guard()->getRecallerName());
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($sessionId, session()->getId());
            $this->assertNotNull($user->fresh()->last_login);
            $this->assertNotEmpty($user->fresh()->getRememberToken());
            Auth::logout();
        }
    }

    public function test_login_without_remember_does_not_issue_a_persistent_cookie(): void
    {
        $user = UserFactory::new()->create(['username' => 'session-user']);
        app(ActivateUserService::class)->activate($user);
        $this->post(route('access.login.post'), ['username' => $user->username, 'password' => 'password123', 'remember' => false], $this->inertiaHeaders())->assertRedirect(route('dashboard.index'))->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertEmpty($user->fresh()->getRememberToken());
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_failures_and_throttle_redirect_with_visible_field_errors(): void
    {
        $user = UserFactory::new()->create(['email' => 'admin@example.test']);
        app(ActivateUserService::class)->activate($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('access.login'))->post(route('access.login.post'), ['username' => 'admin@example.test', 'password' => 'wrong-password'], $this->inertiaHeaders())->assertRedirect(route('access.login'))->assertSessionHasErrors('email');
        }
        $this->from(route('access.login'))->post(route('access.login.post'), ['username' => 'admin@example.test', 'password' => 'wrong-password'], $this->inertiaHeaders())->assertRedirect(route('access.login'))->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
        $this->assertGuest();
        $this->assertNull(session('_old_input.password'));
        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertDontSee('wrong-password');
    }

    public function test_inactive_accounts_cannot_login_and_reset_confirmation_errors_preserve_credentials(): void
    {
        $user = UserFactory::new()->create(['username' => 'inactive-user', 'email' => 'inactive@example.test']);
        $this->from(route('access.login'))->post(route('access.login.post'), ['username' => $user->username, 'password' => 'password123'], $this->inertiaHeaders())->assertRedirect(route('access.login'))->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login);
        $this->withCookie(session()->getName(), session()->getId());
        $this->get(route('access.login'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.errors.username', trans('core/acl::auth.login.not_active'));
        $token = Password::broker()->createToken($user);
        $this->from(route('access.password.reset', ['token' => $token]))->post(route('access.password.reset.post'), ['email' => $user->email, 'token' => $token, 'password' => 'new-password123', 'password_confirmation' => 'different-password'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
        $this->assertGuest();
    }

    public function test_password_recovery_sends_notification_and_rejects_invalid_tokens_then_resets(): void
    {
        Notification::fake();
        $user = UserFactory::new()->superUser()->create(['email' => 'admin@example.test']);
        $this->from(route('access.password.request'))->post(route('access.password.email'), ['email' => $user->email], $this->inertiaHeaders())->assertRedirect(route('access.password.request'))->assertSessionHas('success_msg');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->from(route('access.password.request'))->post(route('access.password.email'), ['email' => 'missing@example.test'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('email');
        $data = ['email' => $user->email, 'password' => 'new-password123', 'password_confirmation' => 'new-password123', 'token' => 'invalid-token'];
        $this->from(route('access.password.reset', ['token' => 'invalid-token']))->post(route('access.password.reset.post'), $data, $this->inertiaHeaders())->assertRedirect()->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $token = Password::broker()->createToken($user);
        $this->post(route('access.password.reset.post'), [...$data, 'token' => $token], $this->inertiaHeaders())->assertRedirect(route('dashboard.index'));
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session('status'));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_expired_and_consumed_reset_tokens_cannot_change_password_again(): void
    {
        $user = UserFactory::new()->create(['email' => 'reset-boundary@example.test']);
        $expiredToken = Password::broker()->createToken($user);
        $this->travel(config('auth.passwords.'.config('auth.defaults.passwords').'.expire') + 1)->minutes();
        $payload = ['email' => $user->email, 'password' => 'new-password123', 'password_confirmation' => 'new-password123'];
        $this->post(route('access.password.reset.post'), [...$payload, 'token' => $expiredToken], $this->inertiaHeaders())
            ->assertRedirect()->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertGuest();
        $this->travelBack();

        $token = Password::broker()->createToken($user);
        $this->post(route('access.password.reset.post'), [...$payload, 'token' => $token], $this->inertiaHeaders())
            ->assertRedirect(route('dashboard.index'));
        Auth::logout();
        $this->post(route('access.password.reset.post'), [...$payload, 'token' => $token, 'password' => 'reused-password123', 'password_confirmation' => 'reused-password123'], $this->inertiaHeaders())
            ->assertRedirect()->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_blocks_protected_routes(): void
    {
        $user = UserFactory::new()->superUser()->create();
        $this->actingAs($user)->withSession(['acceptance-private-state' => 'discard-on-logout']);
        $sessionId = session()->getId();
        $this->get(route('access.logout'), $this->inertiaHeaders())->assertRedirect(route('access.login'));
        $this->assertGuest();
        $this->assertNull(session('acceptance-private-state'));
        $this->assertNotSame($sessionId, session()->getId());
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertStatus(409)->assertHeader('X-Inertia-Location', route('access.login'));
        $this->get(route('settings.general'))->assertRedirect(route('access.login'));
        $this->put(route('settings.general.update'), ['admin_email' => '[]'])->assertRedirect(route('access.login'));
    }
}
