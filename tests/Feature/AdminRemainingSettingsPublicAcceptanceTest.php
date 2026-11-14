<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\Icon\Facades\Icon;
use Kern\Member\Models\Member;
use Kern\Optimize\Facades\OptimizerHelper;
use Kern\Optimize\Supports\Optimizer;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Slug\Models\Slug;
use Kern\SocialLogin\Facades\SocialService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingSettingsPublicAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Mail::fake();
        $this->sourceRoot = base_path();
        $this->fixtureRoot = $this->sourceRoot.'/tmp/acceptance-settings-'.bin2hex(random_bytes(8));
        foreach (['platform/plugins', 'platform/themes', 'public/storage', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs', 'bootstrap/cache', 'lang', 'resources', 'config'] as $directory) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$directory);
        }
        File::copyDirectory(public_path('build'), $this->fixtureRoot.'/public/build');
        File::copy($this->sourceRoot.'/composer.json', $this->fixtureRoot.'/composer.json');
        File::copyDirectory($this->sourceRoot.'/platform/core/base/resources/email-templates', $this->fixtureRoot.'/platform/core/base/resources/email-templates');
        Icon::setIconPath($this->sourceRoot.'/platform/core/icon/resources/svg');
        $this->app->setBasePath($this->fixtureRoot);
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->useBootstrapPath($this->fixtureRoot.'/bootstrap');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config([
            'packages.installer.installer.enabled' => false,
            'filesystems.default' => 'public',
            'core.media.media.disk' => 'public',
            'filesystems.disks.public.root' => $this->fixtureRoot.'/public/storage',
            'filesystems.disks.local.root' => $this->fixtureRoot.'/storage/private',
            'view.compiled' => $this->fixtureRoot.'/storage/framework/views',
            'purifier.cachePath' => $this->fixtureRoot.'/storage/purifier',
            'packages.plugin-management.general.enable_plugin_list_cache' => false,
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');
        Http::preventStrayRequests();
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);
        $this->app->setBasePath($this->sourceRoot);
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function activatePlugin(string $plugin): void
    {
        File::copyDirectory($this->sourceRoot.'/platform/plugins/'.$plugin, plugin_path($plugin));
        $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
    }

    private function publicTheme(): void
    {
        $path = theme_path('acceptance-settings');
        foreach (['public', 'layouts', 'views', 'routes'] as $directory) {
            File::ensureDirectoryExists($path.'/'.$directory);
        }
        File::put($path.'/theme.json', json_encode(['id' => 'test/settings', 'name' => 'Acceptance settings', 'namespace' => 'Theme\\AcceptanceSettings\\', 'version' => '1.0', 'author' => 'Test', 'required_plugins' => []]));
        File::put($path.'/layouts/default.blade.php', '<!DOCTYPE html><html><head>{!! Theme::header() !!}</head><body>{!! apply_filters(THEME_FRONT_BODY, null) !!}{!! Theme::content() !!}{!! Theme::footer() !!}</body></html>');
        File::put($path.'/views/page.blade.php', '<main>{!! BaseHelper::clean($page->content) !!}</main>');
        File::put($path.'/views/post.blade.php', '<main>{!! BaseHelper::clean($post->content) !!}</main>');
        File::put($path.'/routes/web.php', '<?php Theme::routes();');
        File::ensureDirectoryExists(package_path('theme/routes'));
        File::copy($this->sourceRoot.'/platform/packages/theme/routes/public.php', package_path('theme/routes/public.php'));
        $this->assertFalse(app(ThemeService::class)->activate('acceptance-settings')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
    }

    private function page(): Page
    {
        $this->publicTheme();
        $page = Page::query()->create(['name' => 'Settings public proof', 'status' => 'published', 'content' => '<p>Public settings body</p>', 'user_id' => auth()->id()]);
        Slug::query()->create(['key' => 'settings-public-proof', 'prefix' => '', 'reference_type' => Page::class, 'reference_id' => $page->getKey()]);

        return $page;
    }

    #[TestWith(['id'])]
    #[TestWith(['code'])]
    public function test_tracking_mode_save_reopen_and_public_footer_follow_the_blade_contract(string $mode): void
    {
        $page = $this->page();
        $code = '<script id="acceptance-tracking">window.acceptanceTracking=true;</script>';
        $this->put(route('settings.website-tracking.update'), ['google_tag_manager_type' => $mode, 'google_tag_manager_id' => 'G-ACCEPTANCE', 'google_tag_manager_code' => $code], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $fields = collect($this->get(route('settings.website-tracking'), $this->headers())->assertOk()->json('props.fields'))->keyBy('name');
        $this->assertSame($mode, $fields['google_tag_manager_type']['value']);
        $this->assertContains(['name' => 'google_tag_manager_type', 'value' => 'id'], $fields['google_tag_manager_id']['conditions']);
        $this->assertContains(['name' => 'google_tag_manager_type', 'value' => 'code'], $fields['google_tag_manager_code']['conditions']);

        $response = $this->get($page->url)->assertOk();

        if ($mode === 'id') {
            $response->assertSee('gtag/js?id=G-ACCEPTANCE', false)->assertDontSee('acceptance-tracking', false);
        } else {
            $response->assertSee($code, false)->assertDontSee('gtag/js?id=G-ACCEPTANCE', false);
            config(['core.base.general.demo_mode_enabled' => true]);
            $this->get($page->url)->assertOk()->assertDontSee('acceptance-tracking', false);
        }
        Http::assertNothingSent();
    }

    #[TestWith([0, 'optimize_remove_comments'])]
    #[TestWith([1, null])]
    #[TestWith([1, 'optimize_collapse_white_space'])]
    #[TestWith([1, 'optimize_elide_attributes'])]
    #[TestWith([1, 'optimize_inline_css'])]
    #[TestWith([1, 'optimize_insert_dns_prefetch'])]
    #[TestWith([1, 'optimize_remove_comments'])]
    #[TestWith([1, 'optimize_remove_quotes'])]
    #[TestWith([1, 'optimize_defer_javascript'])]
    public function test_optimize_settings_control_actual_public_html_and_keep_preformatted_content(int $enabled, ?string $option): void
    {
        $page = $this->page();
        $layout = '<!DOCTYPE html><html><head>{!! Theme::header() !!}<script src="https://assets.example.test/app.js"></script></head><body><!-- acceptance optimization comment --><section>  <p>  Compact   text </p>  </section><p style="color:red">Styled</p><form method="get"><input disabled="disabled"></form><meta name="acceptance" content="x"><pre> A  B\n C </pre>{!! Theme::content() !!}</body></html>';
        File::put(theme_path('acceptance-settings/layouts/default.blade.php'), str_replace('\n', "\n", $layout));
        $payload = array_fill_keys(['optimize_collapse_white_space', 'optimize_elide_attributes', 'optimize_inline_css', 'optimize_insert_dns_prefetch', 'optimize_remove_comments', 'optimize_remove_quotes', 'optimize_defer_javascript'], 0);
        $payload['optimize_page_speed_enable'] = $enabled;
        if ($option) {
            $payload[$option] = 1;
        }
        $this->put(route('optimize.settings.update'), $payload, $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $fields = collect($this->get(route('optimize.settings'), $this->headers())->assertOk()->json('props.fields'))->keyBy('name');
        $this->assertContains(['name' => 'optimize_page_speed_enable', 'value' => '1'], $fields['optimize_remove_comments']['conditions']);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        OptimizerHelper::clearResolvedInstance(Optimizer::class);

        $html = $this->get($page->url)->assertOk()->getContent();

        $this->assertStringContainsString("<pre> A  B\n C </pre>", $html);
        if (! $enabled || ! $option) {
            $this->assertStringContainsString('<!-- acceptance optimization comment -->', $html);
            $this->assertStringContainsString('style="color:red"', $html);

            return;
        }
        match ($option) {
            'optimize_collapse_white_space' => $this->assertStringContainsString('<section><p> Compact text </p></section>', $html),
            'optimize_elide_attributes' => $this->assertStringNotContainsString('method="get"', $html),
            'optimize_inline_css' => $this->assertMatchesRegularExpression('/\.page_speed_\d+\{color:red\}/', $html),
            'optimize_insert_dns_prefetch' => $this->assertStringContainsString('href="//assets.example.test"', $html),
            'optimize_remove_comments' => $this->assertStringNotContainsString('<!-- acceptance optimization comment -->', $html),
            'optimize_remove_quotes' => $this->assertStringContainsString('name=acceptance', $html),
            'optimize_defer_javascript' => $this->assertMatchesRegularExpression('/<script[^>]*assets\.example\.test\/app\.js[^>]*defer/', $html),
        };
        Http::assertNothingSent();
    }

    private function saveRules(array $changes = []): void
    {
        $this->put(route('settings.rules.update'), array_replace(['email_rules_blacklist_email_domains' => '[]', 'email_rules_blacklist_specified_emails' => '[]', 'email_rules_exception_emails' => '[]', 'email_rules_strict' => 0, 'email_rules_dns' => 0, 'email_rules_spoof' => 0], $changes), $this->headers())->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');
    }

    #[TestWith(['email_rules_blacklist_email_domains', 'blocked.test', 'SENDER@BLOCKED.TEST', false])]
    #[TestWith(['email_rules_blacklist_email_domains', 'BLOCKED.TEST', 'sender@blocked.test', false])]
    #[TestWith(['email_rules_blacklist_specified_emails', 'sender@allowed.test', 'SENDER@ALLOWED.TEST', false])]
    #[TestWith(['email_rules_exception_emails', 'sender@blocked.test', 'SENDER@BLOCKED.TEST', true])]
    #[TestWith(['email_rules_exception_emails', 'SENDER@BLOCKED.TEST', 'sender@blocked.test', true])]
    public function test_saved_email_blacklists_and_exceptions_control_public_contact_validation(string $field, string $listed, string $email, bool $accepted): void
    {
        $this->activatePlugin('contact');
        setting()->forceSet('receiver_emails', '[{"value":"inbox@example.test"}]')->save();
        $this->saveRules(['email_rules_blacklist_email_domains' => '[{"value":"blocked.test"}]', $field => json_encode([['value' => $listed]])]);
        $response = $this->postJson(route('public.send.contact'), ['name' => 'Acceptance Sender', 'email' => $email, 'content' => 'Public email rule proof']);

        if ($accepted) {
            $response->assertOk();
            $this->assertDatabaseCount('contacts', 1);
            $this->assertDatabaseHas('contacts', ['email' => $email]);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertDatabaseCount('contacts', 0);
            Mail::assertNothingSent();
        }
    }

    #[TestWith(['email_rules_strict', 'user(comment)@example.test'])]
    #[TestWith(['email_rules_dns', 'sender@domain.invalid'])]
    #[TestWith(['email_rules_spoof', 'pаypal@example.test'])]
    public function test_email_validation_options_are_disabled_by_default_then_enforced_after_saving(string $field, string $email): void
    {
        $this->activatePlugin('contact');
        setting()->forceSet('receiver_emails', '[{"value":"inbox@example.test"}]')->save();
        $this->saveRules();
        $this->postJson(route('public.send.contact'), ['name' => 'Acceptance Sender', 'email' => $email, 'content' => 'Default rules'])->assertOk();
        $this->assertDatabaseCount('contacts', 1);
        $this->saveRules([$field => 1]);
        $fields = collect($this->get(route('settings.email.rules'), $this->headers())->assertOk()->json('props.fields'))->keyBy('name');
        $this->assertSame(1, $fields[$field]['value']);

        $this->postJson(route('public.send.contact'), ['name' => 'Acceptance Sender', 'email' => $email, 'content' => 'Enabled rules'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('contacts', 1);
    }

    #[TestWith(['email_rules_blacklist_email_domains', '[{"value":"invalid domain"}]'])]
    #[TestWith(['email_rules_blacklist_specified_emails', '[{"value":"invalid-email"}]'])]
    #[TestWith(['email_rules_exception_emails', '[{"value":"invalid-email"}]'])]
    public function test_invalid_email_rule_values_return_a_visible_error_without_changing_saved_rules(string $field, string $value): void
    {
        $this->saveRules(['email_rules_blacklist_email_domains' => '[{"value":"keep.test"}]']);
        $before = setting('email_rules_blacklist_email_domains');

        $this->putJson(route('settings.rules.update'), [$field => $value])->assertOk()->assertJsonPath('error', true);

        $this->assertSame($before, setting('email_rules_blacklist_email_domains'));
    }

    #[TestWith(['{"value":"blocked.test"}'])]
    #[TestWith(['["blocked.test"]'])]
    #[TestWith(['null'])]
    #[TestWith(['[{}]'])]
    #[TestWith(['[{"value":1}]'])]
    #[TestWith([['value' => 'not-a-string']])]
    public function test_malformed_email_rule_tag_shape_is_rejected_before_persisting(mixed $value): void
    {
        $this->saveRules(['email_rules_blacklist_email_domains' => '[{"value":"keep.test"}]']);

        $this->putJson(route('settings.rules.update'), ['email_rules_blacklist_email_domains' => $value])->assertUnprocessable()->assertJsonValidationErrors('email_rules_blacklist_email_domains');

        $this->assertSame('[{"value":"keep.test"}]', setting('email_rules_blacklist_email_domains'));
    }

    public function test_social_style_choices_keep_the_blade_preview_images(): void
    {
        $this->activatePlugin('social-login');
        $fields = collect($this->get(route('social-login.settings'), $this->headers())->assertOk()->json('props.fields'))->keyBy('name');
        $choices = collect($fields['social_login_style']['choices'])->keyBy('value');

        foreach (['default', 'basic', 'minimal'] as $style) {
            $this->assertSame(asset('vendor/core/plugins/social-login/images/styles/'.$style.'.png'), $choices[$style]['image'] ?? null);
        }
    }

    #[TestWith(['default', 'social-login-lg'])]
    #[TestWith(['basic', 'social-login-basic'])]
    #[TestWith(['minimal', 'social-icons'])]
    public function test_social_settings_preserve_conditions_callback_helpers_style_and_public_login_buttons(string $style, string $cssClass): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $this->activatePlugin('social-login');
        File::ensureDirectoryExists(core_path('table/routes'));
        File::copy($this->sourceRoot.'/platform/core/table/routes/web-actions.php', core_path('table/routes/web-actions.php'));
        $this->activatePlugin('member');
        $this->publicTheme();
        Route::getRoutes()->refreshNameLookups();
        SocialService::registerModule(['guard' => 'member', 'model' => Member::class, 'login_url' => route('public.member.login'), 'redirect_url' => route('public.member.dashboard')]);
        $this->put(route('social-login.settings.update'), ['social_login_enable' => 1, 'social_login_google_enable' => 1, 'social_login_google_app_id' => 'fixture-client', 'social_login_google_app_secret' => 'fixture-secret', 'social_login_style' => $style], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $fields = collect($this->get(route('social-login.settings'), $this->headers())->assertOk()->json('props.fields'))->keyBy('name');
        $this->assertContains(['name' => 'social_login_enable', 'value' => '1'], $fields['social_login_google_app_id']['conditions']);
        $this->assertContains(['name' => 'social_login_google_enable', 'value' => '1'], $fields['social_login_google_app_id']['conditions']);
        $this->assertStringContainsString(route('auth.social.callback', 'google'), $fields['social_login_google_helper']['description_html']);
        $this->assertStringContainsString(route('facebook-data-deletion-request-callback'), $fields['social_login_facebook_data_deletion_request_callback_url']['description_html']);
        $this->assertSame('', $fields['social_login_google_app_secret']['value']);
        $this->assertSame($style, $fields['social_login_style']['value']);

        $this->get(route('public.member.login'))->assertOk()->assertSee($cssClass, false)->assertSee(route('auth.social', 'google'), false)->assertDontSee(route('auth.social', 'github'), false);
        $this->put(route('social-login.settings.update'), ['social_login_enable' => 0, 'social_login_google_enable' => 1, 'social_login_google_app_id' => 'fixture-client', 'social_login_style' => $style], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('fixture-secret', setting('social_login_google_app_secret'));
        $this->get(route('public.member.login'))->assertOk()->assertDontSee('Login with social networks');
        Http::assertNothingSent();
    }
}
