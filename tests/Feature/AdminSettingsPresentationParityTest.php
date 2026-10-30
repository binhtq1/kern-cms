<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Kern\Base\Supports\AdminRenderer;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Language\Providers\LanguageServiceProvider;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;
use Twig\TwigFilter;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminSettingsPresentationParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.settings' => 'inertia', 'core.base.admin.modules.translations' => 'inertia']);
        if (! is_plugin_active('translation')) {
            $this->assertFalse(app(PluginService::class)->activate('translation')['error']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_template_schema_preserves_module_groups_descriptions_and_mandatory_toggle_rules(): void
    {
        foreach (['member', 'contact'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $response = $this->get(route('settings.email.template'), $this->inertiaHeaders())->assertOk();
        $templates = collect($response->json('props.templates'))->keyBy('code');
        foreach (['core.acl.password-reminder', 'plugins.member.password-reminder', 'plugins.member.confirm-email'] as $code) {
            $this->assertTrue($templates->has($code), $code);
            $this->assertFalse($templates[$code]['canOff']);
            $this->assertNotEmpty($templates[$code]['groupLabel']);
            $this->assertNotEmpty($templates[$code]['description']);
        }
        $contact = $templates['plugins.contact.notice'];
        $this->assertTrue($contact['canOff']);
        $this->assertSame('plugins.contact', $contact['group']);
        $this->assertSame(trans('plugins/contact::contact.settings.email.title'), $contact['groupLabel']);
        $this->assertSame(trans('plugins/contact::contact.settings.email.templates.notice_description'), $contact['description']);
        $this->assertSame(route('settings.email.template.status.update', ['plugins', 'contact', 'notice']), $contact['statusUrl']);
    }

    public function test_bundled_email_template_language_chooser_keeps_contact_editor_inertia(): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->app->register(LanguageServiceProvider::class, true);
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $fixture = 'acceptance-mail-locale-'.Str::uuid();
        add_filter('setting_email_template_path', fn (string $path): string => $fixture.'/'.$path, 999);
        $parameters = ['plugins', 'contact', 'notice'];
        $englishPath = storage_path('app/email-templates/'.$fixture.'/contact/notice.tpl');
        $viPath = storage_path('app/email-templates/'.$fixture.'/contact/vi/notice.tpl');
        File::ensureDirectoryExists(dirname($englishPath));
        File::put($englishPath, '<p>English notice</p>');
        setting()->set('plugins_contact_notice_subject', 'English subject')->save();
        try {
            $this->get(route('settings.email.template.edit', $parameters), $this->inertiaHeaders())
                ->assertOk()->assertJsonPath('component', 'Settings/EmailTemplate')
                ->assertJsonPath('props.content', '<p>English notice</p>')
                ->assertJsonPath('props.languageNavigation.0.active', true)
                ->assertJsonPath('props.languageNavigation.1.url', route('settings.email.template.edit', [...$parameters, 'ref_lang' => 'vi']));
            \Kern\Language\Facades\Language::swap(new LanguageManager);
            $vi = $this->get(route('settings.email.template.edit', [...$parameters, 'ref_lang' => 'vi']), $this->inertiaHeaders())
                ->assertOk()->assertJsonPath('component', 'Settings/EmailTemplate')->assertJsonPath('props.languageNavigation.1.active', true);
            $vi->assertJsonPath('props.submit', route('settings.email.template.update', [...$parameters, 'ref_lang' => 'vi']))
                ->assertJsonPath('props.restore', route('settings.email.template.restore', [...$parameters, 'ref_lang' => 'vi']))
                ->assertJsonPath('props.preview', route('settings.email.template.preview', [...$parameters, 'ref_lang' => 'vi']));
            $this->withSession(['_token' => 'email-locale-acceptance-token']);
            $this->putJson($vi->json('props.submit'), ['_token' => 'email-locale-acceptance-token', 'module' => 'contact', 'template_file' => 'notice', 'email_subject_key' => $vi->json('props.subjectKey'), 'email_subject' => 'Tiêu đề tiếng Việt', 'email_content' => '<p>Thông báo tiếng Việt</p>'])
                ->assertOk()->assertJsonPath('error', false);
            $this->assertSame('<p>Thông báo tiếng Việt</p>', File::get($viPath));
            $this->assertSame('<p>English notice</p>', File::get($englishPath));
            $this->assertSame('English subject', setting('plugins_contact_notice_subject'));
            \Kern\Language\Facades\Language::swap(new LanguageManager);
            $this->get(route('settings.email.template.edit', [...$parameters, 'ref_lang' => 'vi']), $this->inertiaHeaders())
                ->assertOk()->assertJsonPath('props.content', '<p>Thông báo tiếng Việt</p>')->assertJsonPath('props.subject', 'Tiêu đề tiếng Việt');
            $this->get($vi->json('props.preview'), $this->inertiaHeaders())->assertOk()
                ->assertJsonPath('props.back', route('settings.email.template.edit', [...$parameters, 'ref_lang' => 'vi']))
                ->assertJsonPath('props.iframe', route('settings.email.template.iframe', [...$parameters, 'ref_lang' => 'vi']));
            $this->postJson($vi->json('props.restore'), ['_token' => 'email-locale-acceptance-token', 'module' => 'contact', 'template_file' => 'notice', 'email_subject_key' => $vi->json('props.subjectKey')])
                ->assertOk()->assertJsonPath('error', false);
            $this->assertFileDoesNotExist($viPath);
            $this->assertSame('<p>English notice</p>', File::get($englishPath));
            $this->assertSame('English subject', setting('plugins_contact_notice_subject'));
            add_filter('setting_email_template_meta_boxes', fn (?string $html): string => $html.'<p>Unknown extension controls</p>', 2000);
            $this->get(route('settings.email.template.edit', $parameters), $this->inertiaHeaders())->assertStatus(503)
                ->assertJsonPath('component', 'System/Compatibility');
        } finally {
            File::deleteDirectory(storage_path('app/email-templates/'.$fixture));
        }
    }

    public function test_captcha_password_recovery_form_toggles_remain_checkboxes_and_credentials_stay_masked(): void
    {
        foreach (['captcha', 'member'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        setting()->set('captcha_secret', 'synthetic-private-captcha-secret')->save();
        $response = $this->get(route('captcha.settings'), $this->inertiaHeaders())->assertOk();
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $toggles = $fields->filter(fn (array $field): bool => str_contains($field['name'], 'password'));
        $this->assertGreaterThanOrEqual(4, $toggles->count());
        foreach ($toggles as $field) {
            $this->assertSame('checkbox', $field['type'], $field['name']);
            $this->assertFalse($field['secret'], $field['name']);
            $this->assertContains($field['value'], [0, 1], $field['name']);
        }
        $payload = $fields->reject(fn (array $field): bool => $field['secret'])->mapWithKeys(fn (array $field): array => [$field['name'] => $field['value']])->all();
        $payload['enable_captcha'] = 0;
        $payload['enable_math_captcha'] = 0;
        foreach ($toggles->values() as $index => $field) {
            $payload[$field['name']] = $index % 2;
        }
        $this->putJson(route('captcha.settings.update'), $payload)->assertOk()->assertJsonPath('error', false);
        foreach ($toggles->values() as $index => $field) {
            $this->assertSame((string) ($index % 2), (string) setting($field['name']));
        }
        $this->assertSame('synthetic-private-captcha-secret', setting('captcha_secret'));
        $this->assertSame('password', $fields['captcha_secret']['type']);
        $this->assertTrue($fields['captcha_secret']['secret']);
        $this->assertTrue($fields['captcha_secret']['stored']);
        $this->assertSame('', $fields['captcha_secret']['value']);
        $this->assertStringNotContainsString('synthetic-private-captcha-secret', $response->getContent());
    }

    public function test_email_editor_and_preview_translate_variables_and_expose_template_tools(): void
    {
        $parameters = ['core', 'acl', 'password-reminder'];
        $this->get(route('settings.email.template'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.kind', 'email-template')->assertJsonPath('props.emailNavigation.0.url', route('settings.email'));
        $this->get(route('settings.email.template.edit', $parameters), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.variables.reset_link', trans('core/acl::auth.settings.email.templates.password_reminder.reset_link'))
            ->assertJsonPath('props.back', route('settings.email.template'))
            ->assertJsonPath('props.hasSubject', true)
            ->assertJsonPath('props.emailNavigation.1.url', route('settings.email.template'))
            ->assertJsonStructure(['props' => ['icons', 'functions' => ['if' => ['sample', 'label']]]]);
        $this->get(route('settings.email.template.preview', $parameters), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.variables.reset_link', trans('core/acl::auth.settings.email.templates.password_reminder.reset_link'));
        foreach (['header', 'footer'] as $template) {
            $this->get(route('settings.email.template.edit', ['core', 'base', $template]), $this->inertiaHeaders())->assertOk()
                ->assertJsonPath('props.hasSubject', false)
                ->assertJsonPath('props.title', trans('core/setting::setting.email.template_'.$template));
        }
    }

    public function test_email_template_extension_output_is_blocked_with_compatibility_screen(): void
    {
        add_filter('setting_email_template_meta_boxes', fn ($html, $parameters) => $parameters['module'] === 'acl' && $parameters['template'] === 'password-reminder' ? '<p>Custom email template metabox</p>' : $html, 20, 2);
        $url = route('settings.email.template.edit', ['core', 'acl', 'password-reminder']);
        $this->get($url, $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_media_select_defaults_match_native_blade_and_preserve_stored_choice(): void
    {
        $response = $this->get(route('settings.media'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.kind', 'media');
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $this->assertSame(trans('core/setting::setting.media.enable_chunk'), $fields['media_chunk_enabled']['label']);
        $this->assertSame('Apply watermark to all folders', $fields['media_folders_can_add_watermark_all']['label']);
        $this->assertSame('Add watermark for images in folders:', $fields['media_folders_can_add_watermark']['label']);
        $this->assertContains(['name' => 'media_folders_can_add_watermark_all', 'value' => '0'], $fields['media_folders_can_add_watermark']['conditions']);
        $this->assertSame('0', $fields['media_aws_use_path_style_endpoint']['value']);
        setting()->forceSet('media_aws_use_path_style_endpoint', '1')->save();
        $response = $this->get(route('settings.media'), $this->inertiaHeaders())->assertOk();
        $this->assertSame('1', collect($response->json('props.fields'))->keyBy('name')['media_aws_use_path_style_endpoint']['value']);
        $response = $this->get(route('settings.datatables'), $this->inertiaHeaders())->assertOk();
        $this->assertSame('', collect($response->json('props.fields'))->keyBy('name')['datatables_pagination_type']['value']);
    }

    public function test_sidebar_navigation_keeps_registered_admin_routes_on_react(): void
    {
        config(['core.base.admin.modules.pages' => 'inertia', 'core.base.admin.modules.auth' => 'blade']);
        add_filter('setting_email_template_meta_boxes', fn () => '<p>Legacy extension</p>');
        $this->get(route('settings.email.template.edit', ['core', 'acl', 'password-reminder']), $this->inertiaHeaders())->assertStatus(503);
        $this->assertTrue(app(AdminRenderer::class)->isInertiaRoute('pages.index'));
        $this->assertTrue(app(AdminRenderer::class)->isInertiaRoute('access.login'));
        config(['core.base.admin.modules.pages' => 'blade']);
        $this->assertTrue(app(AdminRenderer::class)->isInertiaRoute('pages.index'));
    }

    public function test_theme_translations_offer_existing_reimport_workflow_and_block_custom_header(): void
    {
        $url = route('translations.theme-translations');
        $this->get($url, $this->inertiaHeaders())->assertOk()->assertJsonPath('props.urls.reimport', route('translations.theme-translations.re-import'))->assertJsonStructure(['props' => ['instruction', 'reimportDescription']]);
        add_filter('translation_theme_translation_header', fn () => '<p>Custom translation header</p>');
        $this->get($url, $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_email_template_actions_cannot_write_unrelated_settings_or_template_files(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['settings.email' => true]]));
        setting()->set('admin_title', 'Protected title')->save();
        $parameters = ['core', 'acl', 'password-reminder'];
        $payload = ['module' => 'acl', 'template_file' => 'password-reminder', 'email_content' => 'Do not save', 'email_subject' => 'Injected', 'email_subject_key' => 'admin_title'];

        $this->putJson(route('settings.email.template.update', $parameters), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email_subject_key');
        $this->postJson(route('settings.email.template.restore', $parameters), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email_subject_key');
        $this->postJson(route('settings.email.template.status.update', $parameters), ['key' => 'admin_title', 'value' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->assertSame('Protected title', setting('admin_title'));

        unset($payload['email_subject_key']);
        $payload['module'] = 'base';
        $payload['template_file'] = 'header';
        $this->putJson(route('settings.email.template.update', $parameters), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['module', 'template_file']);
        $this->postJson(route('settings.email.template.restore', $parameters), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['module', 'template_file']);
    }

    public function test_email_template_status_persists_only_the_selected_template_setting(): void
    {
        $parameters = ['core', 'acl', 'password-reminder'];
        $key = get_setting_email_status_key(...$parameters);
        $this->postJson(route('settings.email.template.status.update', $parameters), ['key' => $key, 'value' => '0'])
            ->assertOk()->assertJsonPath('error', false);
        $this->assertSame('0', setting($key));
        $this->get(route('settings.email'), $this->inertiaHeaders())->assertOk();
    }

    public function test_email_template_syntax_validation_preserves_saved_content_and_extension_filters(): void
    {
        $fixture = 'acceptance-'.Str::uuid();
        add_filter('setting_email_template_path', fn (string $path): string => $fixture.'/'.$path);
        $path = get_setting_email_template_path('acl', 'password-reminder');
        $parameters = ['core', 'acl', 'password-reminder'];
        $key = get_setting_email_subject_key(...$parameters);
        $payload = ['module' => 'acl', 'template_file' => 'password-reminder', 'email_subject_key' => $key, 'email_subject' => 'Saved subject'];
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'Saved body');
        setting()->set($key, 'Saved subject')->save();

        $registered = false;
        add_filter('cms_twig_compiler', function ($compiler) use (&$registered) {
            if (! $registered) {
                $compiler->addFilter(new TwigFilter('acceptance_filter', fn (string $value): string => $value));
                $registered = true;
            }

            return $compiler;
        });

        try {
            $this->putJson(route('settings.email.template.update', $parameters), [...$payload, 'email_content' => '{% if broken %}'])
                ->assertUnprocessable()->assertJsonValidationErrors('email_content');
            $this->assertSame('Saved body', File::get($path));
            $this->assertSame('Saved subject', setting($key));
            $this->putJson(route('settings.email.template.update', $parameters), [...$payload, 'email_content' => 'Do not save', 'email_subject' => '{{ broken'])
                ->assertUnprocessable()->assertJsonValidationErrors('email_subject');
            $this->assertSame('Saved body', File::get($path));
            $this->assertSame('Saved subject', setting($key));

            $content = "{{ 'custom' | acceptance_filter }} {{ 'facebook' | icon_url }} {{ 'text' | trans }}";
            $this->putJson(route('settings.email.template.update', $parameters), [...$payload, 'email_content' => $content])
                ->assertOk()->assertJsonPath('error', false);
            $this->assertSame($content, File::get($path));
            $this->get(route('settings.email.template.edit', $parameters), $this->inertiaHeaders())
                ->assertOk()->assertJsonPath('props.content', $content);
            $this->postJson(route('settings.email.template.restore', $parameters), $payload)
                ->assertOk()->assertJsonPath('error', false);
            $this->assertFileDoesNotExist($path);
        } finally {
            File::deleteDirectory(storage_path('app/email-templates/'.$fixture));
        }
    }

    public function test_email_template_syntax_validation_applies_unconditional_compiler_hooks_for_subject_and_content(): void
    {
        $fixture = 'acceptance-'.Str::uuid();
        add_filter('setting_email_template_path', fn (string $path): string => $fixture.'/'.$path);
        add_filter('cms_twig_compiler', function ($compiler) {
            $compiler->addFilter(new TwigFilter('acceptance_unconditional_filter', fn (string $value): string => $value));

            return $compiler;
        });

        $parameters = ['core', 'acl', 'password-reminder'];
        $payload = [
            'module' => 'acl',
            'template_file' => 'password-reminder',
            'email_subject_key' => get_setting_email_subject_key(...$parameters),
            'email_subject' => "{{ 'subject' | acceptance_unconditional_filter }}",
            'email_content' => "{{ 'content' | acceptance_unconditional_filter }}",
        ];
        $path = get_setting_email_template_path('acl', 'password-reminder');
        File::ensureDirectoryExists(dirname($path));

        try {
            $this->putJson(route('settings.email.template.update', $parameters), $payload)
                ->assertOk()->assertJsonPath('error', false);

            $this->assertSame($payload['email_content'], File::get($path));
            $this->assertSame($payload['email_subject'], setting($payload['email_subject_key']));
        } finally {
            File::deleteDirectory(storage_path('app/email-templates/'.$fixture));
        }
    }
}
