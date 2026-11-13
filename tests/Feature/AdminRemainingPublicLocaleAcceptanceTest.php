<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Kern\Block\Models\Block;
use Kern\Captcha\Facades\Captcha;
use Kern\Contact\Forms\Fronts\ContactForm;
use Kern\Language\Facades\Language;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language as LanguageModel;
use Kern\Language\Providers\LanguageServiceProvider;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Slug\Models\Slug;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Providers\HookServiceProvider;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingPublicLocaleAcceptanceTest extends TestCase
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
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $this->app->register(LanguageServiceProvider::class, true);
        Http::preventStrayRequests();
        Mail::fake();
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

    private function languages(bool $third = false): array
    {
        $en = LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        $vi = LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $fr = $third ? LanguageModel::query()->create(['lang_name' => 'French', 'lang_code' => 'fr', 'lang_locale' => 'fr', 'lang_flag' => 'fr', 'lang_order' => 3, 'lang_is_default' => false]) : null;
        Language::swap(new LanguageManager);

        return [$en, $vi, $fr];
    }

    private function publicGet(string $path, array $headers = []): TestResponse
    {
        session()->flush();
        $this->app->instance('request', Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => $headers['Accept-Language'] ?? 'en']));
        Language::swap(new LanguageManager);
        Language::setLocale();
        LanguageAdvancedManager::initModelRelations();
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();

        return $this->get($path, $headers);
    }

    #[TestWith([true, false, false])]
    #[TestWith([true, false, true])]
    #[TestWith([true, true, false])]
    #[TestWith([true, true, true])]
    #[TestWith([false, false, false])]
    #[TestWith([false, false, true])]
    #[TestWith([false, true, false])]
    #[TestWith([false, true, true])]
    public function test_public_language_prefix_hidden_switcher_and_browser_detection_combinations(bool $hideDefault, bool $hideVietnamese, bool $autoDetect): void
    {
        [$en, $vi] = $this->languages();
        setting()->set(['language_hide_default' => (int) $hideDefault, 'language_hide_languages' => json_encode($hideVietnamese ? [$vi->getKey()] : []), 'language_auto_detect_user_language' => (int) $autoDetect, 'language_display' => 'name'])->save();
        $this->publicTheme();
        $this->post(route('pages.store'), ['name' => 'Locale fixture', 'content' => '<p>Public locale body</p>', 'status' => 'published', 'slug' => 'locale-fixture', 'is_slug_editable' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $target = $hideDefault && $autoDetect && ! $hideVietnamese ? 'vi' : 'en';
        $home = $this->publicGet('/', ['Accept-Language' => 'vi,en;q=0.7']);
        if ($hideDefault && $target === 'en') {
            $home->assertOk();
        } else {
            $home->assertRedirect(url('/'.$target));
        }
        $canonical = ($hideDefault ? '' : '/en').'/locale-fixture';
        $response = $this->publicGet($canonical)->assertOk()->assertSee('Public locale body');
        if ($hideVietnamese) {
            $response->assertDontSee('language_bar_chooser', false);
        } else {
            $response->assertSee('language_bar_chooser', false)->assertSee('Vietnamese');
            $this->assertSame(url('/vi/locale-fixture'), Language::getLocalizedURL('vi', url($canonical), [], false));
        }
        $this->assertSame(url($canonical), Language::getLocalizedURL('en', url($canonical), [], false));
    }

    public function test_default_change_and_secondary_language_deletion_preserve_other_translations_and_public_links(): void
    {
        [$en, $vi, $fr] = $this->languages(true);
        setting()->set(['language_hide_default' => 1, 'language_auto_detect_user_language' => 0])->save();
        $this->publicTheme();
        $this->post(route('pages.store'), ['name' => 'Retained page', 'content' => '<p>Original English body</p>', 'status' => 'published', 'slug' => 'retained-page', 'is_slug_editable' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $page = Page::query()->sole();
        $slug = Slug::query()->where('reference_type', Page::class)->where('reference_id', $page->id)->sole();
        foreach (['vi' => 'Bài viết giữ lại', 'fr' => 'Page française'] as $code => $name) {
            DB::table('pages_translations')->updateOrInsert(['pages_id' => $page->id, 'lang_code' => $code], ['name' => $name, 'content' => '<p>'.$name.'</p>']);
            DB::table('slugs_translations')->updateOrInsert(['slugs_id' => $slug->id, 'lang_code' => $code], ['key' => 'retained-page', 'prefix' => '']);
        }
        $viTranslation = DB::table('pages_translations')->where('lang_code', 'vi')->first();
        $this->post(route('languages.set.default.update'), ['lang_id' => $vi->getKey()], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($vi->fresh()->lang_is_default);
        $this->delete(route('languages.destroy', $fr->getKey()), [], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('languages', ['lang_id' => $fr->getKey()]);
        $this->assertDatabaseMissing('pages_translations', ['lang_code' => 'fr']);
        $this->assertDatabaseHas('languages', ['lang_id' => $en->getKey()]);
        $this->assertSame((array) $viTranslation, (array) DB::table('pages_translations')->where('lang_code', 'vi')->first());
        $this->assertSame('Original English body', strip_tags($page->fresh()->getRawOriginal('content')));
        $this->publicGet('/retained-page')->assertOk();
        $this->publicGet('/en/retained-page')->assertOk()->assertSee('Original English body');
        $this->assertSame(url('/retained-page'), Language::getLocalizedURL('vi', url('/en/retained-page'), [], false));
        $this->assertSame(url('/en/retained-page'), Language::getLocalizedURL('en', url('/retained-page'), [], false));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_old_slug_is_not_found_after_edit_in_both_legacy_and_react_modes(bool $legacy): void
    {
        config(['core.base.admin.legacy_mode' => $legacy]);
        $this->publicTheme();
        $create = ['name' => 'Slug parity page', 'content' => '<p>Slug preserved body</p>', 'status' => 'published', 'slug' => 'original-slug', 'is_slug_editable' => 1];
        if ($legacy) {
            $this->postJson(route('pages.store'), $create)->assertOk()->assertJsonPath('error', false);
        } else {
            $this->post(route('pages.store'), $create, $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        }
        $page = Page::query()->sole();
        $this->publicGet('/original-slug')->assertOk()->assertSee('Slug preserved body');
        if ($legacy) {
            $this->postJson(route('pages.update', $page), [...$create, 'slug' => 'replacement-slug'])->assertOk()->assertJsonPath('error', false);
        } else {
            $this->post(route('pages.update', $page), [...$create, 'slug' => 'replacement-slug'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->publicGet('/original-slug')->assertNotFound();
        $this->publicGet('/replacement-slug')->assertOk()->assertSee('Slug preserved body');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_saved_page_banner_and_lazy_block_render_through_public_compiler_and_endpoint(bool $legacy): void
    {
        config(['core.base.admin.legacy_mode' => $legacy]);
        $this->publicTheme();
        Block::query()->create(['name' => 'Acceptance lazy block', 'alias' => 'acceptance-lazy-block', 'status' => 'published', 'content' => '<p>Acceptance lazy block body</p>']);
        $payload = ['name' => 'Banner lazy acceptance', 'content' => '<p>Acceptance static page body</p>[static-block alias="acceptance-lazy-block" enable_lazy_loading="yes"][/static-block]', 'status' => 'published', 'slug' => 'banner-lazy-acceptance', 'is_slug_editable' => 1, 'banner_image' => 'https://example.test/acceptance-banner.jpg'];
        $saved = $this->post(route('pages.store'), $payload, $legacy ? [] : $this->headers());
        if ($legacy) {
            $saved->assertRedirect();
        } else {
            $saved->assertRedirect()->assertSessionHasNoErrors();
        }
        $page = Page::query()->sole();
        $this->assertSame($payload['banner_image'], $page->getMetaData('banner_image', true));
        $this->publicGet('/banner-lazy-acceptance')->assertOk()->assertSee('Acceptance static page body')
            ->assertSee('data-background="https://example.test/acceptance-banner.jpg"', false)
            ->assertSee('data-name="static-block"', false)->assertSee('acceptance-lazy-block')
            ->assertDontSee('Acceptance lazy block body')->assertDontSee('resources/js/admin.tsx');
        $this->postJson(route('public.ajax.render-ui-block'), ['name' => 'static-block', 'attributes' => ['alias' => 'acceptance-lazy-block', 'enable_lazy_loading' => 'yes']])
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('data', '<p>Acceptance lazy block body</p>');
        $this->assertSame($payload['content'], $page->fresh()->getRawOriginal('content'));
        $this->publicGet('/banner-lazy-acceptance')->assertOk()->assertSee('data-name="static-block"', false)->assertDontSee('Acceptance lazy block body');
        $this->postJson(route('public.ajax.render-ui-block'), ['name' => 'unregistered-acceptance-block'])->assertOk()->assertJsonPath('data', null);
        $this->postJson(route('public.ajax.render-ui-block'), ['name' => 'static-block', 'attributes' => ['alias' => ['invalid']]])->assertUnprocessable()->assertJsonValidationErrors('attributes.alias');
        Http::assertNothingSent();
    }

    public function test_public_custom_assets_render_all_positions_and_survive_renderer_roundtrip(): void
    {
        config(['packages.theme.general.enable_custom_js' => true, 'packages.theme.general.enable_custom_html' => true]);
        $this->publicTheme();
        $this->post(route('pages.store'), ['name' => 'Asset acceptance', 'content' => '<p>Asset public body</p>', 'status' => 'published', 'slug' => 'asset-acceptance', 'is_slug_editable' => 1], $this->headers())->assertRedirect();
        $js = $html = [];
        foreach (['header', 'body', 'footer'] as $position) {
            $js['custom_'.$position.'_js'] = 'window.acceptance_'.$position.' = true;';
            $html['custom_'.$position.'_html'] = '<p id="acceptance-'.$position.'">Acceptance '.$position.'</p>';
        }
        $css = '.acceptance-public { color: #123456; }';
        $this->assertFileDoesNotExist(theme_path('ripple/public/css/style.integration.css'));
        foreach ([false, true, false] as $index => $legacy) {
            config(['core.base.admin.legacy_mode' => $legacy]);
            $headers = $legacy ? [] : $this->headers();
            $this->post(route('theme.custom-js.post'), $js, $headers)->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route('theme.custom-html.post'), $html, $headers)->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route('theme.custom-css.post'), ['custom_css' => $css], $headers)->assertRedirect()->assertSessionHasNoErrors();
            foreach (['js' => $js, 'html' => $html, 'css' => ['custom_css' => $css]] as $type => $values) {
                $editor = $this->get(route('theme.custom-'.$type), $headers)->assertOk();
                foreach ($values as $name => $value) {
                    $fields = $legacy ? $editor->viewData('page')['props']['fields'] : $editor->json('props.fields');
                    $this->assertSame($value, collect($fields)->firstWhere('name', $name)['value']);
                }
            }
            if ($index === 0) {
                $this->app->register(HookServiceProvider::class, true);
            }
            $public = $this->publicGet('/asset-acceptance')->assertOk()->getContent();
            $head = strpos($public, '</head>');
            $body = strpos($public, '<body');
            $header = strpos($public, '<header');
            $footer = strpos($public, '</body>');
            foreach (['header', 'body', 'footer'] as $position) {
                foreach ([$html['custom_'.$position.'_html'], $js['custom_'.$position.'_js']] as $value) {
                    $offset = strpos($public, $value);
                    $this->assertNotFalse($offset);
                    if ($position === 'header') {
                        $this->assertLessThan($head, $offset);
                    } elseif ($position === 'body') {
                        $this->assertGreaterThan($body, $offset);
                        $this->assertLessThan($header, $offset);
                    } else {
                        $this->assertGreaterThan($header, $offset);
                        $this->assertLessThan($footer, $offset);
                    }
                }
            }
            $this->assertStringContainsString('/css/style.integration.css?v=', $public);
            $this->assertSame($css, File::get(Theme::getStyleIntegrationPath()));
        }
        Http::assertNothingSent();
    }

    #[TestWith(['readonly'])]
    #[TestWith(['write-failure'])]
    #[TestWith(['upload-failure'])]
    public function test_robots_failures_preserve_original_bytes_and_report_inertia_error(string $failure): void
    {
        config(['packages.theme.general.enable_robots_txt_editor' => true]);
        $this->publicTheme();
        $path = public_path('robots.txt');
        $original = "User-agent: *\nDisallow: /keep-original";
        File::put($path, $original);
        if ($failure === 'readonly') {
            File::partialMock()->shouldReceive('isWritable')->with($path)->andReturn(false);
            $editor = $this->get(route('theme.robots-txt'), $this->headers())->assertOk();
            $this->assertContains('notice', array_column($editor->json('props.fields'), 'type'));
        } elseif ($failure === 'write-failure') {
            File::partialMock()->shouldReceive('put')->with($path, 'Replacement text')->andReturn(false);
        }
        $input = ['robots_txt_content' => 'Replacement text'];
        if ($failure === 'upload-failure') {
            $fixture = UploadedFile::fake()->createWithContent('replacement.txt', "User-agent: *\nDisallow: /replacement");
            $input['robots_txt_file'] = new class($fixture->getPathname(), 'replacement.txt', 'text/plain', null, true) extends UploadedFile
            {
                public function move(string $directory, ?string $name = null): \Symfony\Component\HttpFoundation\File\File
                {
                    throw new FileException('Acceptance upload failure');
                }
            };
        }
        $this->withCookie(config('session.cookie'), $this->app['session']->driver()->getId());
        $this->post(route('theme.robots-txt.post'), $input, $this->headers())->assertRedirect(route('theme.robots-txt'))->assertSessionHasErrors('_request')->assertSessionHas('error_msg')->assertSessionMissing('success_msg');
        $this->assertSame($original, File::get($path));
        $this->withCookie(config('session.cookie'), $this->app['session']->driver()->getId());
        $this->get(route('theme.robots-txt'), $this->headers())->assertOk()->assertJsonStructure(['props' => ['errors' => ['_request']]]);
    }

    public function test_robots_text_upload_precedence_and_legacy_json_error_contracts(): void
    {
        config(['packages.theme.general.enable_robots_txt_editor' => true]);
        $this->publicTheme();
        $path = public_path('robots.txt');
        File::put($path, 'Original robots');
        $this->post(route('theme.robots-txt.post'), ['robots_txt_content' => 'Updated text'], $this->headers())->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success_msg');
        $this->assertSame('Updated text', File::get($path));
        $uploaded = "User-agent: *\nDisallow: /uploaded";
        $this->postJson(route('theme.robots-txt.post'), ['robots_txt_content' => 'Must not override upload', 'robots_txt_file' => UploadedFile::fake()->createWithContent('robots.txt', $uploaded)])->assertOk()->assertJsonPath('error', false);
        $this->assertSame($uploaded, File::get($path));
        config(['core.base.admin.legacy_mode' => true]);
        $editor = $this->get(route('theme.robots-txt'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Editor');
        $this->assertSame($uploaded, collect($editor->json('props.fields'))->firstWhere('name', 'robots_txt_content')['value']);
        File::partialMock()->shouldReceive('isWritable')->with($path)->andReturn(false);
        $this->postJson(route('theme.robots-txt.post'), ['robots_txt_content' => 'Rejected JSON'])->assertOk()->assertJsonPath('error', true);
        $this->assertSame($uploaded, File::get($path));
        $this->get(route('theme.robots-txt'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Editor');
    }

    public function test_contact_blacklist_rejects_case_insensitive_whole_words_without_writes_or_mail(): void
    {
        $this->publicTheme();
        setting()->set(['blacklist_keywords' => '[{"value":"spam"}]', 'receiver_emails' => '[{"value":"inbox@example.test"}]'])->save();
        $payload = ['name' => 'Public Sender', 'email' => 'sender@example.test', 'content' => 'This is a SPAM message'];
        $this->postJson(route('public.send.contact'), $payload)->assertOk()->assertJsonPath('error', true);
        $this->assertDatabaseCount('contacts', 0);
        Mail::assertNothingOutgoing();
        $this->postJson(route('public.send.contact'), [...$payload, 'content' => 'A spammer is a separate word'])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_configured_math_captcha_rejects_missing_and_wrong_answers_then_accepts_correct_answer(): void
    {
        $this->publicTheme();
        setting()->set(['enable_captcha' => 0, 'enable_math_captcha' => 1, Captcha::formSettingKey(ContactForm::class, 'enable_math_captcha') => 1, 'receiver_emails' => '[{"value":"inbox@example.test"}]'])->save();
        $payload = ['name' => 'Captcha Sender', 'email' => 'captcha@example.test', 'content' => 'A legitimate contact message'];
        $session = ['math-captcha' => ['first' => 2, 'second' => 7, 'operand' => '+']];
        $this->withSession($session)->postJson(route('public.send.contact'), $payload)->assertUnprocessable()->assertJsonValidationErrors('math-captcha');
        $this->withSession($session)->postJson(route('public.send.contact'), [...$payload, 'math-captcha' => '99'])->assertUnprocessable()->assertJsonValidationErrors('math-captcha');
        $this->assertDatabaseCount('contacts', 0);
        Mail::assertNothingOutgoing();
        $this->withSession($session)->postJson(route('public.send.contact'), [...$payload, 'math-captcha' => '9'])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('contacts', ['email' => 'captcha@example.test', 'content' => $payload['content']]);
    }
}
