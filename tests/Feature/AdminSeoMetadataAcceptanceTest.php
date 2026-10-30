<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Kern\Blog\Models\Post;
use Kern\Language\Facades\Language;
use Kern\Language\Models\Language as LanguageModel;
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
class AdminSeoMetadataAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);

        foreach (['blog'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }

        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
            'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json'))
                ? hash_file('sha256', public_path('build/admin/manifest.json'))
                : '',
        ];
    }

    protected function enablePublicTheme(): void
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
    }

    public function test_public_seo_uses_default_metadata_when_language_plugin_has_no_language_records(): void
    {
        $this->enablePublicTheme();
        $this->assertSame(0, LanguageModel::query()->count());
        $oldSlug = 'acceptance-public-contract-old';
        $newSlug = 'acceptance-public-contract-new';
        $this->post(route('pages.store'), [
            'name' => 'Acceptance public contract',
            'content' => '<p>Public page content</p>',
            'status' => 'published',
            'template' => 'default',
            'slug' => $oldSlug,
            'is_slug_editable' => 1,
        ], $this->headers())->assertRedirect();
        $page = Page::query()->where('name', 'Acceptance public contract')->firstOrFail();

        $this->post(route('pages.update', $page), [
            'name' => $page->name,
            'content' => $page->content,
            'status' => 'published',
            'template' => 'default',
            'slug' => $newSlug,
            'is_slug_editable' => 1,
            'image' => 'https://example.test/page-image.jpg',
            'seo_meta' => [
                'seo_title' => 'Acceptance social title',
                'seo_description' => 'Acceptance social description',
                'seo_image' => 'https://example.test/social-image.jpg',
                'index' => 'noindex',
            ],
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('meta_boxes', ['reference_type' => Page::class, 'reference_id' => $page->getKey(), 'meta_key' => 'seo_meta']);
        $this->assertDatabaseMissing('meta_boxes', ['reference_type' => Page::class, 'reference_id' => $page->getKey(), 'meta_key' => '_seo_meta']);
        $this->assertSame('noindex', $page->fresh()->getMetaData('seo_meta', true)['index'] ?? null);
        $this->assertSame('https://example.test/social-image.jpg', $page->fresh()->getMetaData('seo_meta', true)['seo_image'] ?? null);
        $oldResponse = $this->get('/'.$oldSlug);
        $oldResponse->assertNotFound();
        $publicPage = $this->get('/'.$newSlug)->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSee('https://example.test/social-image.jpg', false)
            ->assertSee('Acceptance social title', false);
        $this->assertStringContainsString('Acceptance social description', $publicPage->getContent());

        $sitemap = $this->get(route('public.sitemap.index', ['key' => 'pages', 'extension' => 'xml']))->assertOk();
        $sitemap->assertSee('/'.$newSlug)->assertDontSee('/'.$oldSlug);
    }

    #[TestWith(['pages', Page::class])]
    #[TestWith(['posts', Post::class])]
    public function test_seo_image_nested_legacy_precedence_clear_and_omitted_contracts(string $routePrefix, string $modelClass): void
    {
        $this->post(route($routePrefix.'.store'), [
            'name' => 'SEO image contract', 'content' => '<p>Content</p>', 'status' => 'published',
            'seo_meta' => ['seo_title' => 'Social title', 'seo_image' => 'https://example.test/nested.jpg'],
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $model = $modelClass::query()->where('name', 'SEO image contract')->firstOrFail();
        $this->assertSame('https://example.test/nested.jpg', $model->fresh()->getMetaData('seo_meta', true)['seo_image']);

        $payload = ['name' => $model->name, 'status' => 'published', 'seo_meta' => ['seo_title' => 'Social title', 'seo_image' => 'https://example.test/nested.jpg']];
        $this->post(route($routePrefix.'.update', $model), [...$payload, 'seo_meta_image' => 'https://example.test/legacy.jpg'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('https://example.test/legacy.jpg', $model->fresh()->getMetaData('seo_meta', true)['seo_image']);

        $this->post(route($routePrefix.'.update', $model), ['name' => $model->name, 'status' => 'published'], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('https://example.test/legacy.jpg', $model->fresh()->getMetaData('seo_meta', true)['seo_image']);

        foreach (['', null] as $emptyImage) {
            $this->post(route($routePrefix.'.update', $model), [...$payload, 'seo_meta_image' => $emptyImage], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
            $meta = $model->fresh()->getMetaData('seo_meta', true);
            $this->assertArrayNotHasKey('seo_image', $meta);
            $this->assertSame('Social title', $meta['seo_title']);
        }

        $this->post(route($routePrefix.'.update', $model), [...$payload, 'seo_meta' => ['seo_title' => 'Social title', 'seo_image' => '']], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('seo_image', $model->fresh()->getMetaData('seo_meta', true));
    }

    public function test_configured_locale_seo_metadata_remains_separate_and_retrievable(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        foreach (['en' => true, 'vi' => false] as $code => $default) {
            LanguageModel::query()->create(['lang_name' => $code, 'lang_locale' => $code, 'lang_code' => $code, 'lang_is_default' => $default, 'lang_flag' => $code === 'en' ? 'us' : 'vn', 'lang_order' => $default ? 1 : 2]);
        }
        Language::setSupportedLocales([
            'en' => ['lang_name' => 'English', 'lang_code' => 'en', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'],
            'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn'],
        ]);
        $this->post(route('pages.store'), ['name' => 'Localized SEO', 'status' => 'published', 'seo_meta' => ['seo_title' => 'Default SEO', 'seo_image' => 'https://example.test/default.jpg']], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $page = Page::query()->where('name', 'Localized SEO')->firstOrFail();
        $this->post(route('pages.update', $page).'?ref_lang=vi', ['name' => 'SEO tiếng Việt', 'status' => 'published', 'language' => 'vi', 'seo_meta' => ['seo_title' => 'Tiêu đề SEO', 'seo_image' => 'https://example.test/vi.jpg', 'index' => 'noindex']], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('meta_boxes', ['reference_type' => Page::class, 'reference_id' => $page->getKey(), 'meta_key' => 'seo_meta']);
        $this->assertDatabaseHas('meta_boxes', ['reference_type' => Page::class, 'reference_id' => $page->getKey(), 'meta_key' => 'vi_seo_meta']);
        $this->get(route('pages.edit', $page).'?ref_lang=vi', $this->headers())->assertOk()
            ->assertJsonPath('props.page.seo_meta.seo_title', 'Tiêu đề SEO')
            ->assertJsonPath('props.page.seo_meta.seo_image', 'https://example.test/vi.jpg');
        $this->get(route('pages.edit', $page), $this->headers())->assertOk()
            ->assertJsonPath('props.page.seo_meta.seo_title', 'Default SEO')
            ->assertJsonPath('props.page.seo_meta.seo_image', 'https://example.test/default.jpg');
    }
}
