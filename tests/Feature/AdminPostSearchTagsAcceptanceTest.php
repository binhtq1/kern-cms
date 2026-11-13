<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Models\User;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Blog\Models\Tag;
use Kern\Language\Facades\Language;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language as LanguageModel;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Member\Models\Member;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminPostSearchTagsAcceptanceTest extends TestCase
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

    public function test_post_search_matches_category_and_both_author_types_without_leaking_status(): void
    {
        $cmsAuthor = UserFactory::new()->create(['first_name' => 'Cora', 'last_name' => 'Quartz']);
        $member = Member::query()->create(['first_name' => 'Mira', 'last_name' => 'Linden', 'email' => 'mira@example.test', 'password' => 'unused']);
        $category = Category::query()->create(['name' => 'Ceramics', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $categoryPost = Post::query()->create(['name' => 'First story', 'status' => 'published']);
        $categoryPost->categories()->attach($category);
        $cmsPost = Post::query()->create(['name' => 'Second story', 'status' => 'published', 'author_id' => $cmsAuthor->id, 'author_type' => User::class]);
        $memberPost = Post::query()->create(['name' => 'Third story', 'status' => 'published', 'author_id' => $member->id, 'author_type' => Member::class]);
        $draft = Post::query()->create(['name' => 'Hidden draft', 'status' => 'draft', 'author_id' => $member->id, 'author_type' => Member::class]);
        $draft->categories()->attach($category);
        foreach (['Ceramics' => $categoryPost, 'Cora' => $cmsPost, 'Quartz' => $cmsPost, 'Cora Quartz' => $cmsPost, 'Mira' => $memberPost, 'Linden' => $memberPost, 'Mira Linden' => $memberPost] as $search => $expected) {
            $this->get(route('posts.index', ['search' => $search, 'status' => 'published']), $this->headers())
                ->assertOk()->assertJsonCount(1, 'props.items.data')->assertJsonPath('props.items.data.0.id', (string) $expected->id);
        }
    }

    public function test_post_search_preserves_translated_title_and_permission_boundary(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        LanguageModel::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        LanguageModel::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        Language::swap(new LanguageManager);
        Language::setSupportedLocales(['en' => ['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_is_default' => true, 'lang_flag' => 'us'], 'vi' => ['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_is_default' => false, 'lang_flag' => 'vn']]);
        Language::setLocale('vi');
        Language::setCurrentAdminLocale('vi');
        LanguageAdvancedManager::initModelRelations();
        $category = Category::query()->create(['name' => 'Ceramics', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $post = Post::query()->create(['name' => 'English title', 'status' => 'published']);
        $post->categories()->attach($category);
        DB::table('posts_translations')->insert(['posts_id' => $post->id, 'lang_code' => 'vi', 'name' => 'Bài viết tiếng Việt']);
        foreach (['Ceramics', 'tiếng Việt'] as $search) {
            $this->get(route('posts.index', ['search' => $search, 'status' => 'published', 'ref_lang' => 'vi']), $this->headers())->assertOk()->assertJsonCount(1, 'props.items.data')->assertJsonPath('props.items.data.0.name', 'Bài viết tiếng Việt');
        }
        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.index' => true]]));
        $this->get(route('posts.index', ['search' => 'Ceramics']), $this->headers())->assertForbidden();
    }

    public function test_tags_schema_offers_registered_tags_and_save_preserves_existing_tags(): void
    {
        $tag = Tag::query()->create(['name' => 'Laravel', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $post = Post::query()->create(['name' => 'Tags story', 'status' => 'published']);
        $post->tags()->attach($tag);
        $response = $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.tag', 'Laravel');
        $field = collect($response->json('props.fields'))->firstWhere('name', 'tag');
        $this->assertSame('tags', $field['type']);
        $this->assertContains(['value' => 'Laravel', 'label' => 'Laravel'], $field['choices']);
        $this->post(route('posts.update', $post), ['name' => $post->name, 'status' => 'published', 'tag' => json_encode([['value' => 'Laravel'], ['value' => 'New tag']])])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['Laravel', 'New tag'], $post->tags()->orderBy('name')->pluck('name')->all());
        $this->assertSame(1, Tag::query()->where('name', 'Laravel')->count());
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.tag', 'Laravel, New tag');
        $commaTag = Tag::query()->create(['name' => 'Comma,inside', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $post->tags()->attach($commaTag);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.tag', 'Laravel, New tag, Comma,inside');
        config(['core.base.admin.legacy_mode' => true]);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('component', 'Posts/Form')
            ->assertJsonPath('props.values.tag', 'Laravel, New tag, Comma,inside');
    }

    public function test_unselected_member_author_keeps_cms_author_when_ids_overlap(): void
    {
        $member = Member::query()->create(['id' => auth()->id(), 'first_name' => 'Member', 'last_name' => 'One', 'email' => 'member@example.test', 'password' => 'unused']);
        $this->post(route('posts.store'), ['name' => 'CMS author story', 'status' => 'published', 'author_id' => '', 'author_type' => Member::class, 'tag' => '[]'])->assertSessionHasNoErrors()->assertRedirect();
        $post = Post::query()->where('name', 'CMS author story')->firstOrFail();
        $this->assertSame(User::class, $post->author_type);
        $this->assertEquals(auth()->id(), $post->author_id);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.author_id', '');
        $this->post(route('posts.update', $post), ['name' => 'CMS author updated', 'status' => 'published', 'author_id' => '', 'author_type' => Member::class, 'tag' => '[]'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(User::class, $post->fresh()->author_type);
        $this->assertEquals(auth()->id(), $post->fresh()->author_id);
        $this->post(route('posts.update', $post), ['name' => 'Member author selected', 'status' => 'published', 'author_id' => $member->id, 'tag' => '[]'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Member::class, $post->fresh()->author_type);
        $this->get(route('posts.edit', $post), $this->headers())->assertOk()->assertJsonPath('props.values.author_id', $member->id);
    }
}
