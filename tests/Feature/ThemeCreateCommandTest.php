<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Blog\Models\Tag;
use Kern\PluginManagement\Services\PluginService;
use Kern\Slug\Models\Slug;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ThemeCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceRoot;

    private string $fixtureRoot;

    private string $originalPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceRoot = base_path();
        $this->originalPublicPath = $this->app->publicPath();
        $this->fixtureRoot = sys_get_temp_dir().'/kern-theme-create-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->fixtureRoot.'/platform/themes');
        $this->app->setBasePath($this->fixtureRoot);
        config(['packages.installer.installer.enabled' => false]);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->sourceRoot);
        $this->app->usePublicPath($this->originalPublicPath);
        File::deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    public function test_command_creates_a_standalone_theme_with_vite_entrypoints(): void
    {
        $this->artisan('cms:theme:create', ['name' => 'My-Theme'])->assertSuccessful();

        $root = $this->fixtureRoot.'/platform/themes/my-theme';
        $metadata = json_decode(File::get($root.'/theme.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('kern/my-theme', $metadata['id']);
        $this->assertSame('My Theme', $metadata['name']);
        $this->assertSame('Theme\\MyTheme\\', $metadata['namespace']);
        $this->assertSame(['assets/sass/style.scss', 'assets/js/app.js'], $metadata['vite']['entrypoints']);
        $this->assertSame([], $metadata['required_plugins']);
        foreach (['config.php', 'routes/web.php', 'layouts/default.blade.php', 'partials/header.blade.php', 'partials/footer.blade.php', 'partials/posts.blade.php', 'views/index.blade.php', 'views/page.blade.php', 'views/post.blade.php', 'views/category.blade.php', 'views/tag.blade.php', 'views/search.blade.php', 'assets/sass/style.scss', 'assets/js/app.js'] as $path) {
            $this->assertFileExists($root.'/'.$path);
        }
        $this->assertFileDoesNotExist($root.'/webpack.mix.js');
        $this->assertNotSame('my-theme', setting('theme'));
    }

    #[TestWith(['../escape'])]
    #[TestWith(['/absolute'])]
    #[TestWith(['bad_name'])]
    #[TestWith(['bad--name'])]
    #[TestWith(['123-theme'])]
    #[TestWith(['name-'])]
    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'])]
    public function test_invalid_names_are_rejected_without_creating_a_theme(string $name): void
    {
        $this->artisan('cms:theme:create', ['name' => $name])->assertFailed();

        $this->assertSame([], File::directories($this->fixtureRoot.'/platform/themes'));
    }

    public function test_existing_theme_is_not_overwritten(): void
    {
        $root = $this->fixtureRoot.'/platform/themes/existing';
        File::ensureDirectoryExists($root);
        File::put($root.'/theme.json', 'original content');

        $this->artisan('cms:theme:create', ['name' => 'existing'])->assertFailed();

        $this->assertSame('original content', File::get($root.'/theme.json'));
        $this->assertFileDoesNotExist($root.'/config.php');
    }

    public function test_existing_symbolic_link_is_not_followed(): void
    {
        $target = $this->fixtureRoot.'/existing-target';
        File::ensureDirectoryExists($target);
        File::put($target.'/theme.json', 'original content');
        symlink($target, $this->fixtureRoot.'/platform/themes/linked');

        $this->artisan('cms:theme:create', ['name' => 'linked'])->assertFailed();

        $this->assertSame('original content', File::get($target.'/theme.json'));
        $this->assertFileDoesNotExist($target.'/config.php');
    }

    public function test_failed_scaffolding_removes_only_the_partially_created_theme(): void
    {
        $existing = $this->fixtureRoot.'/platform/themes/existing';
        File::ensureDirectoryExists($existing);
        File::put($existing.'/theme.json', 'original content');
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('copy')->once()->andReturn(false);
        $this->app->instance(Filesystem::class, $files);

        $this->artisan('cms:theme:create', ['name' => 'unfinished'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->fixtureRoot.'/platform/themes/unfinished');
        $this->assertSame('original content', File::get($existing.'/theme.json'));
    }

    public function test_missing_starter_templates_do_not_create_an_incomplete_theme(): void
    {
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('allFiles')->once()->andReturn([]);
        $this->app->instance(Filesystem::class, $files);

        $this->artisan('cms:theme:create', ['name' => 'incomplete'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->fixtureRoot.'/platform/themes/incomplete');
    }

    public function test_created_theme_can_build_activate_and_render_a_public_page(): void
    {
        do {
            $name = 'starter-'.bin2hex(random_bytes(6));
            $sourceTheme = $this->sourceRoot.'/platform/themes/'.$name;
            $buildOutput = $this->sourceRoot.'/public/themes/'.$name;
        } while (file_exists($sourceTheme) || is_link($sourceTheme) || file_exists($buildOutput) || is_link($buildOutput));
        $this->app->setBasePath($this->sourceRoot);
        File::ensureDirectoryExists($this->fixtureRoot.'/public');
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $created = false;

        try {
            $this->artisan('cms:theme:create', ['name' => $name])->assertSuccessful();
            $created = true;
            $build = new Process(['npm', 'run', 'build:theme', '--', $name], $this->sourceRoot, timeout: 60);
            $build->run();
            $this->assertTrue($build->isSuccessful(), $build->getOutput().$build->getErrorOutput());
            $this->assertFileExists($sourceTheme.'/public/build/manifest.json');
            $manifest = json_decode(File::get($sourceTheme.'/public/build/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $styleFile = $manifest["platform/themes/$name/assets/sass/style.scss"]['file'];
            $scriptFile = $manifest["platform/themes/$name/assets/js/app.js"]['file'];
            $this->assertFileExists($sourceTheme.'/public/build/'.$styleFile);
            $this->assertFileExists($sourceTheme.'/public/build/'.$scriptFile);

            $plugin = app(PluginService::class)->activate('blog');
            $this->assertFalse($plugin['error'], $plugin['message']);
            $result = app(ThemeService::class)->activate($name);
            $this->assertFalse($result['error'], $result['message']);
            $this->assertFileExists($this->fixtureRoot.'/public/themes/'.$name.'/build/manifest.json');
            $this->app->register(ThemeManagementServiceProvider::class, true);
            $this->app->register(RouteServiceProvider::class, true);
            Route::getRoutes()->refreshNameLookups();

            $this->get('/')->assertOk()->assertSee('Welcome')
                ->assertSee('/themes/'.$name.'/build/'.$styleFile, false)
                ->assertSee('/themes/'.$name.'/build/'.$scriptFile, false);

            shortcode()->register('starter-proof', 'Starter proof', null, fn () => '<strong>Shortcode rendered</strong>');
            $page = PageFactory::new()->create(['name' => 'Starter page', 'content' => '<p>Starter content</p>[starter-proof][/starter-proof]']);
            Slug::query()->create(['key' => 'starter-page', 'prefix' => '', 'reference_type' => $page::class, 'reference_id' => $page->getKey()]);
            $this->get($page->url)->assertOk()->assertSee('Starter page')->assertSee('Starter content')->assertSee('Shortcode rendered');

            $author = UserFactory::new()->create();
            $authorFields = ['author_id' => $author->getKey(), 'author_type' => $author::class];
            $post = Post::query()->create(['name' => 'Starter article', 'content' => 'Article content', 'status' => 'published', ...$authorFields]);
            $category = Category::query()->create(['name' => 'Starter category', 'status' => 'published', ...$authorFields]);
            $tag = Tag::query()->create(['name' => 'Starter tag', 'status' => 'published', ...$authorFields]);
            foreach ([$post, $category, $tag] as $model) {
                Slug::query()->create(['key' => str($model->name)->slug()->toString(), 'prefix' => '', 'reference_type' => $model::class, 'reference_id' => $model->getKey()]);
            }
            $post->categories()->attach($category);
            $post->tags()->attach($tag);

            $this->get($post->url)->assertOk()->assertSee('Starter article')->assertSee('Article content');
            $this->get($category->url)->assertOk()->assertSee('Starter category')->assertSee('Starter article');
            $this->get($tag->url)->assertOk()->assertSee('Starter tag')->assertSee('Starter article');
            $this->get(route('public.search', ['q' => 'Starter']))->assertOk()->assertSee('Starter article');
        } finally {
            if ($created) {
                File::deleteDirectory($sourceTheme);
                File::deleteDirectory($buildOutput);
            }
        }
    }
}
