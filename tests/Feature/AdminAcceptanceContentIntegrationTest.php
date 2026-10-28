<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\Block\Models\Block;
use Kern\Blog\Models\Post;
use Kern\CustomField\Models\CustomField;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Models\FieldItem;
use Kern\Gallery\Models\Gallery;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Language\Facades\Language;
use Kern\Language\Models\Language as LanguageModel;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminAcceptanceContentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);

        foreach (['block', 'blog', 'custom-field', 'gallery'] as $plugin) {
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

    public function test_gallery_admin_save_and_reset_render_ordered_captions_without_deleting_source_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('acceptance/gallery-first.jpg', 'first-source');
        Storage::disk('public')->put('acceptance/gallery-second.jpg', 'second-source');

        $images = [
            ['img' => 'https://example.test/gallery-second.jpg', 'description' => 'Second caption'],
            ['img' => 'https://example.test/gallery-first.jpg', 'description' => 'First caption'],
        ];

        $this->post(route('galleries.store'), [
            'name' => 'Acceptance gallery',
            'description' => '<p>Acceptance gallery description</p>',
            'order' => 2,
            'status' => 'published',
            'slug' => 'acceptance-gallery',
            'is_slug_editable' => 1,
            'gallery' => json_encode($images),
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('galleries.index'));

        $gallery = Gallery::query()->where('name', 'Acceptance gallery')->firstOrFail();
        $this->assertSame(
            $images,
            GalleryMeta::query()
                ->where('reference_type', Gallery::class)
                ->where('reference_id', $gallery->getKey())
                ->firstOrFail()
                ->images
        );

        $this->enablePublicTheme();
        $publicResponse = $this->get($gallery->url)->assertOk();
        $publicResponse->assertSeeInOrder([
            'gallery-second.jpg',
            'Second caption',
            'gallery-first.jpg',
            'First caption',
        ]);

        $this->post(route('galleries.update', $gallery), [
            'name' => 'Acceptance gallery',
            'description' => '<p>Acceptance gallery description</p>',
            'order' => 2,
            'status' => 'published',
            'slug' => 'acceptance-gallery',
            'is_slug_editable' => 1,
            'gallery' => json_encode([]),
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('galleries.index'));

        $this->assertSame([], gallery_meta_data($gallery->fresh()));
        Storage::disk('public')->assertExists([
            'acceptance/gallery-first.jpg',
            'acceptance/gallery-second.jpg',
        ]);

        $this->get($gallery->fresh()->url)->assertOk()
            ->assertDontSee('gallery-first.jpg')
            ->assertDontSee('gallery-second.jpg');
    }

    public function test_saved_static_block_shortcode_renders_public_raw_content_after_admin_reload(): void
    {
        $rawContent = '<section data-acceptance-block="footer"><h2>Public block body</h2><p>Vietnamese: Nội dung</p></section>';

        $this->post(route('block.store'), [
            'name' => 'Acceptance footer',
            'alias' => 'acceptance-footer',
            'description' => 'Footer used by acceptance test',
            'content' => '[custom][/custom]',
            'raw_content' => $rawContent,
            'status' => 'published',
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('block.index'));

        $block = Block::query()->where('alias', 'acceptance-footer')->firstOrFail();
        $this->get(route('block.edit', $block), $this->headers())
            ->assertOk()
            ->assertJsonPath('props.values.raw_content', $rawContent);

        $this->enablePublicTheme();
        $this->post(route('pages.store'), [
            'name' => 'Acceptance block page',
            'content' => shortcode()->generateShortcode('static-block', ['alias' => 'acceptance-footer']),
            'status' => 'published',
            'template' => 'default',
            'slug' => 'acceptance-block-page',
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('pages.index'));

        $page = Page::query()->where('name', 'Acceptance block page')->firstOrFail();
        $shortcode = shortcode()->generateShortcode('static-block', ['alias' => 'acceptance-footer']);
        $this->assertSame($shortcode, $page->content);
        $this->assertSame(
            '<section><h2>Public block body</h2><p>Vietnamese: Nội dung</p></section>',
            do_shortcode($page->content)->toHtml()
        );

        $response = $this->get($page->url)->assertOk();
        $this->assertTrue(
            str_contains($response->getContent(), 'Public block body'),
            'The public page response omitted the rendered static-block body.'
        );
        $this->assertTrue(str_contains($response->getContent(), 'Nội dung'));
    }

    public function test_page_custom_field_types_save_and_reopen_with_values_intact(): void
    {
        $group = FieldGroup::query()->create([
            'title' => 'Acceptance page field types',
            'status' => 'published',
            'order' => 1,
            'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Page::class]]]),
        ]);

        $fieldTypes = [
            'text' => 'Text value',
            'number' => '42',
            'email' => 'acceptance@example.test',
            'password' => 'private-value',
            'textarea' => 'Textarea value',
            'select' => 'option-b',
            'radio' => 'yes',
            'checkbox' => ['choice-a', 'choice-b'],
            'date' => '2026-10-16',
            'time' => '09:30',
            'color' => '#AF0F26',
            'image' => 'acceptance/page-image.jpg',
            'file' => 'acceptance/page-file.pdf',
            'wysiwyg' => '<p>WYSIWYG value</p>',
            'repeater' => null,
        ];

        $fields = [];
        foreach ($fieldTypes as $type => $value) {
            $fields[$type] = FieldItem::query()->create([
                'field_group_id' => $group->getKey(),
                'title' => ucfirst($type),
                'slug' => $type,
                'type' => $type,
                'options' => '{}',
                'order' => count($fields) + 1,
            ]);
        }
        $repeaterChild = FieldItem::query()->create([
            'field_group_id' => $group->getKey(),
            'parent_id' => $fields['repeater']->getKey(),
            'title' => 'Slide caption',
            'slug' => 'slide_caption',
            'type' => 'text',
            'options' => '{}',
            'order' => 1,
        ]);

        $this->post(route('pages.store'), [
            'name' => 'Acceptance custom-field page',
            'content' => '<p>Page with custom fields</p>',
            'status' => 'published',
            'template' => 'default',
            'slug' => 'acceptance-custom-field-page',
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('pages.index'));
        $page = Page::query()->where('name', 'Acceptance custom-field page')->firstOrFail();
        $form = $this->get(route('pages.edit', $page), $this->headers())->assertOk()
            ->assertJsonCount(1, 'props.customFieldGroups');
        $customFields = $form->json('props.customFieldGroups');
        $values = [
            ...$fieldTypes,
            'repeater' => [[[
                'id' => (string) $repeaterChild->getKey(),
                'slug' => 'slide_caption',
                'type' => 'text',
                'value' => 'First slide caption',
            ]]],
        ];
        foreach ($customFields[0]['items'] as &$item) {
            $item['value'] = $values[$item['slug']];
        }
        unset($item);

        $this->post(route('pages.update', $page), [
            'name' => $page->name,
            'content' => $page->content,
            'status' => 'published',
            'template' => 'default',
            'custom_fields' => json_encode([['id' => $group->getKey(), 'items' => $customFields[0]['items']]]),
            'submitter' => 'apply',
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $reopened = $this->get(route('pages.edit', $page), $this->headers())->assertOk();
        $reopenedValues = collect($reopened->json('props.customFieldGroups.0.items'))->keyBy('slug');
        foreach ($fieldTypes as $type => $value) {
            if ($type === 'repeater') {
                continue;
            }
            $expectedValue = $type === 'checkbox' ? json_encode($value) : $value;
            $this->assertSame($expectedValue, $reopenedValues[$type]['value'], 'Unexpected value after reopening '.$type.' field.');
        }
        $this->assertSame('First slide caption', $reopenedValues['repeater']['value'][0][0]['value']);
    }

    public function test_post_custom_field_types_save_and_reopen_with_values_intact(): void
    {
        $group = FieldGroup::query()->create([
            'title' => 'Acceptance post field types',
            'status' => 'published',
            'order' => 1,
            'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Post::class]]]),
        ]);

        $fieldTypes = [
            'text' => 'Post text',
            'number' => '42',
            'email' => 'post@example.test',
            'password' => 'private-value',
            'textarea' => 'Post textarea',
            'select' => 'option-b',
            'radio' => 'yes',
            'checkbox' => ['choice-a', 'choice-b'],
            'date' => '2026-10-16',
            'time' => '09:30',
            'color' => '#AF0F26',
            'image' => 'acceptance/post-image.jpg',
            'file' => 'acceptance/post-file.pdf',
            'wysiwyg' => '<p>Post WYSIWYG</p>',
            'repeater' => null,
        ];

        $fields = [];
        foreach ($fieldTypes as $type => $value) {
            $fields[$type] = FieldItem::query()->create([
                'field_group_id' => $group->getKey(),
                'title' => ucfirst($type),
                'slug' => $type,
                'type' => $type,
                'options' => '{}',
                'order' => count($fields) + 1,
            ]);
        }
        $repeaterChild = FieldItem::query()->create([
            'field_group_id' => $group->getKey(),
            'parent_id' => $fields['repeater']->getKey(),
            'title' => 'Post slide caption',
            'slug' => 'slide_caption',
            'type' => 'text',
            'options' => '{}',
            'order' => 1,
        ]);

        $this->post(route('posts.store'), [
            'name' => 'Acceptance post custom fields',
            'content' => '<p>Post with custom fields</p>',
            'status' => 'published',
            'slug' => 'acceptance-post-custom-fields',
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('posts.index'));

        $post = Post::query()->where('name', 'Acceptance post custom fields')->firstOrFail();
        $form = $this->get(route('posts.edit', $post), $this->headers())->assertOk()
            ->assertJsonCount(1, 'props.customFieldGroups');
        $customFields = $form->json('props.customFieldGroups');
        $values = [
            ...$fieldTypes,
            'repeater' => [[[
                'id' => (string) $repeaterChild->getKey(),
                'slug' => 'slide_caption',
                'type' => 'text',
                'value' => 'Post slide caption',
            ]]],
        ];
        foreach ($customFields[0]['items'] as &$item) {
            $item['value'] = $values[$item['slug']];
        }
        unset($item);

        $this->post(route('posts.update', $post), [
            'name' => $post->name,
            'content' => $post->content,
            'status' => 'published',
            'custom_fields' => json_encode([['id' => $group->getKey(), 'items' => $customFields[0]['items']]]),
            'submitter' => 'apply',
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $reopened = $this->get(route('posts.edit', $post), $this->headers())->assertOk();
        $reopenedValues = collect($reopened->json('props.customFieldGroups.0.items'))->keyBy('slug');
        foreach ($fieldTypes as $type => $value) {
            if ($type === 'repeater') {
                continue;
            }
            $expectedValue = $type === 'checkbox' ? json_encode($value) : $value;
            $this->assertSame($expectedValue, $reopenedValues[$type]['value'], 'Unexpected value after reopening Post '.$type.' field.');
        }
        $this->assertSame('Post slide caption', $reopenedValues['repeater']['value'][0][0]['value']);
    }

    public function test_page_custom_field_values_are_saved_per_locale_and_fall_back_to_default_when_untranslated(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $pluginService = app(PluginService::class);
        foreach (['language', 'language-advanced'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse($pluginService->activate($plugin)['error'], $plugin);
            }
        }
        LanguageAdvancedManager::registerModule(CustomField::class, ['value']);

        LanguageModel::query()->create(['lang_name' => 'English', 'lang_locale' => 'en', 'lang_code' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us', 'lang_order' => 1]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_locale' => 'vi', 'lang_code' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn', 'lang_order' => 2]);
        Language::setSupportedLocales([
            'en' => ['lang_name' => 'English', 'lang_code' => 'en', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'],
            'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn'],
        ]);

        $group = FieldGroup::query()->create([
            'title' => 'Acceptance translated fields',
            'status' => 'published',
            'rules' => json_encode([[['name' => 'model_name', 'type' => '==', 'value' => Page::class]]]),
        ]);
        $translatedField = FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Subtitle', 'slug' => 'subtitle', 'type' => 'text', 'options' => '{}', 'order' => 1]);
        $fallbackField = FieldItem::query()->create(['field_group_id' => $group->getKey(), 'title' => 'Fallback text', 'slug' => 'fallback_text', 'type' => 'text', 'options' => '{}', 'order' => 2]);

        $this->post(route('pages.store'), [
            'name' => 'Acceptance locale custom fields',
            'content' => '<p>English content</p>',
            'status' => 'published',
            'template' => 'default',
            'slug' => 'acceptance-locale-custom-fields',
            'is_slug_editable' => 1,
        ], $this->headers())->assertRedirect();
        $page = Page::query()->where('name', 'Acceptance locale custom fields')->firstOrFail();
        $groups = $this->get(route('pages.edit', $page), $this->headers())->assertOk()->json('props.customFieldGroups');
        $groups[0]['items'][0]['value'] = 'Default subtitle';
        $groups[0]['items'][1]['value'] = 'Default fallback';
        $this->post(route('pages.update', $page), [
            'name' => $page->name,
            'content' => $page->content,
            'status' => 'published',
            'custom_fields' => json_encode($groups),
        ], $this->headers())->assertRedirect();

        $translatedGroups = $this->get(route('pages.edit', $page).'?ref_lang=vi', $this->headers())
            ->assertOk()->assertJsonPath('props.language', 'vi')->json('props.customFieldGroups');
        $translatedGroups[0]['items'][0]['value'] = 'Tiêu đề phụ';
        $this->post(route('pages.update', $page).'?ref_lang=vi', [
            'name' => 'Acceptance locale custom fields Vietnamese',
            'content' => '<p>Nội dung tiếng Việt</p>',
            'language' => 'vi',
            'status' => 'published',
            'template' => 'default',
            'slug' => 'acceptance-locale-custom-fields-vi',
            'custom_fields' => json_encode($translatedGroups),
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $defaultValue = CustomField::query()->where('use_for', Page::class)->where('use_for_id', $page->getKey())->where('field_item_id', $translatedField->getKey())->firstOrFail();
        $fallbackValue = CustomField::query()->where('use_for', Page::class)->where('use_for_id', $page->getKey())->where('field_item_id', $fallbackField->getKey())->firstOrFail();
        $this->assertSame('Default subtitle', $defaultValue->value);
        $this->assertSame('Default fallback', $fallbackValue->value);
        $this->assertDatabaseHas('custom_fields_translations', ['custom_fields_id' => $defaultValue->getKey(), 'lang_code' => 'vi', 'value' => 'Tiêu đề phụ']);
        $localeForm = $this->get(route('pages.edit', $page).'?ref_lang=vi', $this->headers())->assertOk();
        $localeValues = collect($localeForm->json('props.customFieldGroups.0.items'))->keyBy('slug');
        $this->assertSame('Tiêu đề phụ', $localeValues['subtitle']['value'], 'A translated field should reopen with its locale-specific value.');
        $this->assertSame('Default fallback', $localeValues['fallback_text']['value'], 'A field without a locale value should fall back to the default value.');
    }

    public function test_react_page_save_updates_public_slug_seo_and_sitemap_contracts(): void
    {
        $this->enablePublicTheme();
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

    public function test_public_blog_search_returns_published_posts_and_excludes_draft_matches(): void
    {
        $this->enablePublicTheme();
        $this->post(route('posts.store'), [
            'name' => 'Acceptance searchable published article',
            'content' => '<p>Searchable public content</p>',
            'status' => 'published',
            'slug' => 'acceptance-searchable-published-article',
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('posts.index'));
        $this->post(route('posts.store'), [
            'name' => 'Acceptance searchable draft article',
            'content' => '<p>Searchable private draft content</p>',
            'status' => 'draft',
            'slug' => 'acceptance-searchable-draft-article',
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('posts.index'));

        $response = $this->get(route('public.search', ['q' => 'Acceptance searchable']))->assertOk();
        $response->assertSee('Acceptance searchable published article');
        $response->assertDontSee('Acceptance searchable draft article');
    }

    public function test_public_routes_hide_draft_and_pending_pages_and_posts(): void
    {
        $this->enablePublicTheme();

        foreach ([Page::class, Post::class] as $modelClass) {
            $table = (new $modelClass)->getTable();

            foreach (['draft', 'pending'] as $status) {
                $slug = 'acceptance-'.$table.'-'.$status;
                $record = $modelClass::query()->create([
                    'name' => "Acceptance {$status} {$table}",
                    'content' => '<p>Private status contract</p>',
                    'status' => $status,
                    'slug' => $slug,
                    'is_slug_editable' => 1,
                    ...($modelClass === Page::class ? ['template' => 'default'] : []),
                ]);

                $this->assertDatabaseHas($table, ['id' => $record->getKey(), 'status' => $status]);
                $this->get('/'.$slug)->assertNotFound();
            }
        }
    }

    public function test_robots_txt_editor_reads_and_writes_only_inside_the_isolated_public_root(): void
    {
        $this->enablePublicTheme();
        $isolatedPublicRoot = sys_get_temp_dir().'/kern-admin-robots-'.bin2hex(random_bytes(8));
        $originalPublicRoot = $this->app->publicPath();

        try {
            File::ensureDirectoryExists($isolatedPublicRoot);
            $this->assertTrue(File::copyDirectory($originalPublicRoot.'/build/admin', $isolatedPublicRoot.'/build/admin'));
            $this->app->usePublicPath($isolatedPublicRoot);
            File::put($isolatedPublicRoot.'/robots.txt', "User-agent: *\nDisallow: /private\n");

            $this->get(route('theme.robots-txt'))->assertOk();
            $editor = $this->get(route('theme.robots-txt'), $this->headers())
                ->assertOk()
                ->assertJsonPath('component', 'Theme/Editor');
            $this->assertSame("User-agent: *\nDisallow: /private\n", collect($editor->json('props.fields'))->firstWhere('name', 'robots_txt_content')['value']);

            $this->post(route('theme.robots-txt.post'), ['robots_txt_content' => "User-agent: *\nAllow: /"])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame("User-agent: *\nAllow: /", File::get($isolatedPublicRoot.'/robots.txt'));

            $editor = $this->get(route('theme.robots-txt'), $this->headers())
                ->assertOk()
                ->assertJsonPath('component', 'Theme/Editor');
            $this->assertSame("User-agent: *\nAllow: /", collect($editor->json('props.fields'))->firstWhere('name', 'robots_txt_content')['value']);
        } finally {
            $this->app->usePublicPath($originalPublicRoot);
            File::deleteDirectory($isolatedPublicRoot);
        }
    }
}
