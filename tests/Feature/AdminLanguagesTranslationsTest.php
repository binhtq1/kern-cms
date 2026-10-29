<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\Blog\Models\Post;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Language\Providers\LanguageServiceProvider;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\PluginManagement\Services\PluginService;
use Kern\Setting\Facades\Setting;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Kern\Translation\Exporters\OtherTranslationExporter;
use Kern\Translation\Manager;
use Kern\Translation\Services\GetGroupedTranslationsService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminLanguagesTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected string $temporaryLangPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
        foreach (['language', 'language-advanced', 'translation'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                app(PluginService::class)->activate($plugin);
            }
        }
        config(['core.base.admin.modules.languages' => 'inertia', 'core.base.admin.modules.translations' => 'inertia']);
        $this->temporaryLangPath = storage_path('framework/testing/admin-locales-'.uniqid());
        foreach (['en', 'en_US', 'vi', 'vendor/core', 'vendor/packages', 'vendor/plugins'] as $folder) {
            File::ensureDirectoryExists($this->temporaryLangPath.'/'.$folder);
        }
        $this->app->useLangPath($this->temporaryLangPath);
        Lang::getLoader()->addPath($this->temporaryLangPath);
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryLangPath);
        parent::tearDown();
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_languages_support_create_edit_settings_default_delete_and_rollback(): void
    {
        $response = $this->get(route('languages.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Languages/Index');
        $presets = collect($response->json('props.presets'))->keyBy('lang_code');
        $this->assertSame('Tiếng Việt', $presets['vi']['lang_name']);
        $this->assertSame('vi', $presets['vi']['lang_locale']);
        $this->assertSame('vn', $presets['vi']['lang_flag']);
        $this->assertFalse($presets['vi']['lang_is_rtl']);
        $this->assertTrue($presets['ar']['lang_is_rtl']);
        $english = ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_rtl' => false, 'lang_order' => 1];
        $this->post(route('languages.store'), $english, $this->headers())->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error_msg');
        $en = Language::query()->where('lang_code', 'en_US')->firstOrFail();
        $this->assertTrue($en->lang_is_default);
        $this->post(route('languages.store'), [...$english, 'lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2], $this->headers())->assertRedirect();
        $vi = Language::query()->where('lang_code', 'vi')->firstOrFail();
        $this->post(route('languages.edit'), [...$english, 'lang_id' => $en->getKey(), 'lang_name' => 'English updated'], $this->headers())->assertRedirect();
        $this->assertSame('English updated', $en->fresh()->lang_name);
        $this->post(route('languages.settings'), ['language_hide_default' => 0, 'language_display' => 'name', 'language_switcher_display' => 'list', 'language_hide_languages' => [(string) $vi->getKey()], 'language_auto_detect_user_language' => 1], $this->headers())->assertRedirect();
        $this->assertSame('name', Setting::get('language_display'));
        $this->post(route('languages.set.default.update'), ['lang_id' => $vi->getKey()], $this->headers())->assertRedirect();
        $this->assertTrue($vi->fresh()->lang_is_default);
        $this->delete(route('languages.destroy', $en->getKey()), [], $this->headers())->assertRedirect(route('languages.index'));
        $this->assertDatabaseMissing('languages', ['lang_id' => $en->getKey()]);
        config(['core.base.admin.modules.languages' => 'blade']);
        $this->get(route('languages.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Languages/Index');
    }

    public function test_translation_write_invalidates_only_changed_php_file_with_opcache_enabled(): void
    {
        if (! function_exists('opcache_compile_file')) {
            $this->markTestSkipped('OPcache extension is unavailable.');
        }
        File::put($this->temporaryLangPath.'/en/acceptance.php', '<?php return ["next" => "Next"];');
        File::put($this->temporaryLangPath.'/vi/acceptance.php', '<?php return ["next" => "Before"];');
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
require_once $argv[1].'/platform/plugins/translation/src/Manager.php';
$app = new Illuminate\Foundation\Application($argv[1]);
$app->useLangPath($argv[2]);
$app->instance('config', new Illuminate\Config\Repository(['plugins' => ['translation' => ['general' => []]]]));
$files = new Illuminate\Filesystem\Filesystem;
$app->instance('files', $files);
$loader = new Illuminate\Translation\FileLoader($files, $argv[2]);
$app->instance('translator', new Illuminate\Translation\Translator($loader, 'en'));
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$path = $argv[2].'/vi/acceptance.php';
if (! opcache_compile_file($path) || ! opcache_is_script_cached($path)) {
    throw new RuntimeException('The translation file was not cached.');
}
$before = require $path;
$manager = new Kern\Translation\Manager($app, $files);
$manager->updateTranslation('vi', 'acceptance', 'next', 'After');
$after = require $path;
echo json_encode(['before' => $before['next'], 'after' => $after['next'], 'english' => (require $argv[2].'/en/acceptance.php')['next']], JSON_THROW_ON_ERROR);
PHP;
        $process = new Process([PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.file_update_protection=0', '-d', 'opcache.validate_timestamps=0', '-r', $script, base_path(), $this->temporaryLangPath], base_path(), timeout: 15);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame(['before' => 'Before', 'after' => 'After', 'english' => 'Next'], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_translation_edit_refreshes_existing_file_value_and_flashes_success_on_same_cookie_redirect(): void
    {
        File::put($this->temporaryLangPath.'/en/acceptance.php', '<?php return ["next" => "Next"];');
        File::put($this->temporaryLangPath.'/vi/acceptance.php', '<?php return ["next" => "Tiếp"];');
        $index = route('translations.index', ['ref_lang' => 'vi', 'group' => 'acceptance', 'search' => '', 'page' => 1]);
        $this->get($index, $this->headers())->assertOk()->assertJsonPath('props.items.data.0.value', 'Tiếp');
        $response = $this->from($index)->post(route('translations.group.edit'), ['group' => 'acceptance', 'name' => 'vi|next', 'pk' => 'vi', 'value' => 'Tiếp nghiệm thu'], $this->headers());
        $response->assertRedirect($index)->assertSessionHasNoErrors()->assertSessionHas('success_msg', trans('core/base::notices.update_success_message'));
        $this->get($response->headers->get('Location'), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.value', 'Tiếp nghiệm thu')->assertJsonPath('props.flash.success', trans('core/base::notices.update_success_message'));
        $this->get($index, $this->headers())->assertOk()->assertJsonPath('props.items.data.0.value', 'Tiếp nghiệm thu')->assertJsonPath('props.flash.success', null);
        $this->assertSame('Tiếp nghiệm thu', (require $this->temporaryLangPath.'/vi/acceptance.php')['next']);
        $this->assertSame('Next', (require $this->temporaryLangPath.'/en/acceptance.php')['next']);
    }

    public function test_translations_edit_known_keys_and_reject_paths_and_unknown_imports(): void
    {
        $this->get(route('translations.index', ['ref_lang' => 'vi', 'search' => 'Save']), $this->headers())->assertOk()->assertJsonPath('component', 'Translations/Index');
        $row = app(GetGroupedTranslationsService::class)->handle()->first(fn ($item) => is_string($item['value']));
        $this->post(route('translations.group.edit'), ['group' => $row['group'], 'name' => 'vi|'.$row['key'], 'value' => 'Translated for test'], $this->headers())->assertRedirect();
        $this->get(route('translations.index', ['ref_lang' => 'vi', 'search' => 'Translated for test']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1);
        $this->post(route('translations.group.edit'), ['group' => '../../outside', 'name' => '../outside|key', 'value' => 'Injected'], $this->headers())->assertSessionHasErrors(['group', 'locale']);
        $this->post(route('translations.react.import', ['ref_lang' => 'vi']), ['rows' => [['group' => $row['group'], 'key' => $row['key'], 'value' => 'Imported for test']]], $this->headers())->assertRedirect();
        $this->get(route('translations.index', ['ref_lang' => 'vi', 'search' => 'Imported for test']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1);
        $this->post(route('translations.react.import', ['ref_lang' => 'vi']), ['rows' => [['group' => '../../outside', 'key' => 'unknown', 'value' => 'Injected']]], $this->headers())->assertSessionHasErrors('rows');
        $export = $this->get(route('translations.react.export', ['ref_lang' => 'vi']))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="translations-vi.json"')->json();
        $this->assertGreaterThan(1000, count($export));
        $this->post(route('translations.react.import', ['ref_lang' => 'vi']), ['rows' => $export], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $locales = $this->get(route('translations.locales'), $this->headers())->assertOk()->assertJsonPath('component', 'Locales/Index');
        $existing = collect($locales->json('props.existing'))->keyBy('locale');
        $this->assertTrue($existing['en']['is_default']);
        $this->assertFalse($existing['vi']['is_default']);
        config(['core.base.admin.modules.translations' => 'blade']);
        $this->get(route('translations.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Translations/Index');
    }

    public function test_content_translation_preserves_original_fields_and_relations(): void
    {
        app(PluginService::class)->activate('blog');
        config(['core.base.admin.modules.posts' => 'inertia', 'core.base.admin.modules.categories' => 'inertia']);
        $english = ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_is_rtl' => false, 'lang_order' => 1];
        $this->post(route('languages.store'), $english, $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('languages.store'), [...$english, 'lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        \Kern\Language\Facades\Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        $this->post(route('posts.store'), ['name' => 'Default editorial post', 'description' => 'Default description', 'content' => '<p>English content</p>', 'status' => 'published', 'tag' => json_encode([['value' => 'Default tag']]), 'slug' => 'default-editorial-post', 'is_slug_editable' => 1, 'submitter' => 'save'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $post = Post::query()->firstOrFail();
        $this->get(route('posts.edit', $post).'?ref_lang=vi', $this->headers())->assertOk()->assertJsonPath('props.translatable', true)->assertJsonPath('props.language', 'vi');
        $this->post(route('posts.update', $post).'?ref_lang=vi', ['name' => 'Bài viết tiếng Việt', 'description' => 'Mô tả', 'content' => '<p>Nội dung tiếng Việt</p>', 'status' => 'draft', 'tag' => '[]', 'categories' => [], 'seo_meta' => ['seo_title' => 'Tiêu đề'], 'slug' => 'bai-viet-tieng-viet', 'submitter' => 'save'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('posts', ['id' => $post->getKey(), 'name' => 'Default editorial post', 'content' => '<p>English content</p>', 'status' => 'published']);
        $this->assertDatabaseHas('posts_translations', ['posts_id' => $post->getKey(), 'lang_code' => 'vi', 'name' => 'Bài viết tiếng Việt']);
        $this->assertSame(['Default tag'], $post->fresh()->tags()->pluck('name')->all());
        $this->assertDatabaseHas('slugs_translations', ['lang_code' => 'vi', 'key' => 'bai-viet-tieng-viet']);
        $this->get(route('posts.edit', $post).'?ref_lang=vi', $this->headers())->assertOk()->assertJsonPath('props.values.slug', 'bai-viet-tieng-viet');
        $this->get(route('posts.index', ['ref_lang' => 'vi', 'search' => 'tiếng Việt']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1)->assertJsonPath('props.items.data.0.name', 'Bài viết tiếng Việt');
        $this->post(route('posts.bulk').'?ref_lang=vi', ['ids' => [$post->getKey()], 'action' => 'status', 'status' => 'draft'], $this->headers())->assertSessionHasErrors('status');
        $this->post(route('posts.store'), ['name' => 'Second post', 'status' => 'published', 'slug' => 'second-post', 'is_slug_editable' => 1], $this->headers())->assertRedirect();
        $second = Post::query()->whereKeyNot($post->getKey())->firstOrFail();
        $this->post(route('posts.update', $second).'?ref_lang=vi', ['name' => 'Must roll back', 'status' => 'published', 'slug' => 'bai-viet-tieng-viet'], $this->headers())->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('posts_translations', ['posts_id' => $second->getKey(), 'name' => 'Must roll back']);
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->app->instance('request', Request::create('/vi/bai-viet-tieng-viet'));
        \Kern\Language\Facades\Language::swap(new LanguageManager);
        \Kern\Language\Facades\Language::setLocale('vi');
        $this->app->register(LanguageServiceProvider::class, true);
        LanguageAdvancedManager::initModelRelations();
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->assertSame('/vi/bai-viet-tieng-viet', parse_url(route('public.single', 'bai-viet-tieng-viet'), PHP_URL_PATH));
        $this->get('/vi/bai-viet-tieng-viet')->assertOk()->assertSee('Nội dung tiếng Việt')->assertDontSee('resources/js/admin.tsx');

    }

    public function test_locale_and_language_actions_require_permissions_and_invalid_language_is_rejected(): void
    {
        $this->post(route('languages.store'), ['lang_name' => 'Invalid', 'lang_code' => '../../bad', 'lang_locale' => '../../bad', 'lang_flag' => '../../bad', 'lang_is_rtl' => false, 'lang_order' => -1], $this->headers())->assertSessionHasErrors(['lang_code', 'lang_locale', 'lang_flag', 'lang_order']);
        $this->assertDatabaseCount('languages', 0);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['languages.index' => true]]));
        $this->post(route('languages.set.default.update'), ['lang_id' => 1], $this->headers())->assertForbidden();
        $this->get(route('translations.locales'), $this->headers())->assertForbidden();
        $this->post(route('translations.react.import', ['ref_lang' => 'vi']), ['rows' => []], $this->headers())->assertForbidden();
    }

    public function test_translation_csv_round_trip_preserves_unselected_locales_and_rejects_unknown_keys(): void
    {
        Storage::fake('local');
        $row = app(GetGroupedTranslationsService::class)->handle()->first(fn ($item) => is_string($item['value']));
        $manager = app(Manager::class);
        $manager->updateTranslation('vi', $row['group'], $row['key'], 'Vietnamese before');
        $manager->updateTranslation('en_US', $row['group'], $row['key'], 'Untouched other locale');
        $otherPath = lang_path('vendor/'.dirname($row['group']).'/en_US/'.basename($row['group']).'.php');
        $otherBefore = File::get($otherPath);
        $key = $row['group'].'::'.$row['key'];
        $exported = (new OtherTranslationExporter)->collection()->firstWhere('key', $key);
        $this->assertSame('Vietnamese before', $exported['vi']);
        Storage::disk('local')->put('data-synchronize/translations.csv', 'key,vi'."\n".$key.',Vietnamese after'."\n");
        $payload = ['file_name' => 'translations.csv', 'offset' => 0, 'limit' => 100];
        $validation = $this->postJson(route('tools.data-synchronize.import.other-translations.validate'), $payload)->assertOk();
        $this->assertFalse($validation->json('error'), $validation->getContent());
        $validation->assertJsonPath('data.errors', []);
        $this->assertSame($otherBefore, File::get($otherPath));
        $import = $this->postJson(route('tools.data-synchronize.import.other-translations.store'), $payload)->assertOk();
        $this->assertFalse($import->json('error'), $import->getContent());
        $this->assertSame($otherBefore, File::get($otherPath));
        $this->assertSame('Vietnamese after', (new OtherTranslationExporter)->collection()->firstWhere('key', $key)['vi']);
        Storage::disk('local')->put('data-synchronize/unknown.csv', "key,vi\n../../outside::unknown,Injected\n");
        $this->postJson(route('tools.data-synchronize.import.other-translations.store'), [...$payload, 'file_name' => 'unknown.csv'])
            ->assertOk()->assertJsonPath('error', true);
        $this->assertFileDoesNotExist(lang_path('outside.php'));
        $this->assertSame($otherBefore, File::get($otherPath));
    }

    public function test_translation_import_preserves_locale_column_case_from_export(): void
    {
        Storage::fake('local');
        $row = app(GetGroupedTranslationsService::class)->handle()->first(fn ($item) => is_string($item['value']));
        $key = $row['group'].'::'.$row['key'];
        Storage::disk('local')->put('data-synchronize/regional.csv', 'key,en_US'."\n".$key.',Regional updated'."\n");
        $response = $this->postJson(route('tools.data-synchronize.import.other-translations.store'), ['file_name' => 'regional.csv', 'offset' => 0, 'limit' => 100])->assertOk();
        $this->assertFalse($response->json('error'), $response->getContent());
        $this->assertSame('Regional updated', (new OtherTranslationExporter)->collection()->firstWhere('key', $key)['en_US']);
    }

    public function test_theme_translation_import_requires_source_key_before_writing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('data-synchronize/no-source.csv', "vi\nMust not save\n");
        $payload = ['file_name' => 'no-source.csv', 'offset' => 0, 'limit' => 100];
        $this->postJson(route('tools.data-synchronize.import.theme-translations.validate'), $payload)
            ->assertOk()->assertJsonPath('error', false)->assertJsonCount(1, 'data.errors');
        $this->postJson(route('tools.data-synchronize.import.theme-translations.store'), $payload)
            ->assertOk()->assertJsonPath('error', true);
    }

    public function test_theme_csv_import_counts_only_present_rows_and_preserves_other_locale_files(): void
    {
        Storage::fake('local');
        $manager = app(Manager::class);
        Theme::setThemeName('ripple');
        $theme = Theme::getThemeName();
        $viPath = lang_path("vendor/themes/$theme/vi.json");
        $regionalPath = lang_path("vendor/themes/$theme/en_US.json");
        File::ensureDirectoryExists(dirname($viPath));
        $english = $manager->getThemeTranslations('en');
        $keys = array_slice(array_keys($english), 0, 2);
        $this->assertCount(2, $keys);
        File::put($viPath, json_encode([$keys[0] => 'Old value', $keys[1] => 'Keep this value']));
        File::put($regionalPath, json_encode([$keys[0] => 'Untouched regional', $keys[1] => 'Another regional']));
        $regionalBefore = File::get($regionalPath);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['en', 'vi'], escape: '');
        fputcsv($stream, [$keys[0], 'Imported value'], escape: '');
        rewind($stream);
        Storage::disk('local')->put('data-synchronize/theme.csv', stream_get_contents($stream));
        fclose($stream);
        $payload = ['file_name' => 'theme.csv', 'offset' => 0, 'limit' => 100];
        $this->postJson(route('tools.data-synchronize.import.theme-translations.validate'), $payload)
            ->assertOk()->assertJsonPath('data.errors', []);
        $response = $this->postJson(route('tools.data-synchronize.import.theme-translations.store'), $payload)->assertOk();
        $this->assertFalse($response->json('error'), $response->getContent());
        $response->assertJsonPath('data.total', 1);
        $this->assertSame('Imported value', $manager->getThemeTranslations('vi')[$keys[0]]);
        $this->assertSame('Keep this value', $manager->getThemeTranslations('vi')[$keys[1]]);
        $this->assertSame($regionalBefore, File::get($regionalPath));
    }
}
