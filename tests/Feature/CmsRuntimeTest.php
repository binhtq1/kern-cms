<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Kern\ACL\Services\ActivateUserService;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Page\Models\Page;
use Tests\TestCase;

class CmsRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
    }

    public function test_guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('access.login'));
        $this->get('/admin/login')->assertOk()->assertSee('Kern');
    }

    public function test_view_providers_skip_missing_directories_and_load_existing_views(): void
    {
        $directory = sys_get_temp_dir().'/kern-provider-views-'.bin2hex(random_bytes(8));
        $provider = new class($this->app, $directory) extends ServiceProvider
        {
            use LoadAndPublishDataTrait;

            public function __construct(Application $app, private string $viewPath)
            {
                parent::__construct($app);
                $this->namespace = 'fixture-views';
            }

            protected function getViewsPath(): string
            {
                return $this->viewPath;
            }

            public function boot(): void
            {
                $this->loadAndPublishViews();
            }
        };

        $provider->boot();
        $this->assertArrayNotHasKey('fixture-views', app('view')->getFinder()->getHints());
        $this->assertArrayNotHasKey($directory, ServiceProvider::pathsToPublish($provider::class, 'cms-views'));

        try {
            File::ensureDirectoryExists($directory);
            File::put($directory.'/proof.blade.php', 'Provider view loaded');
            $provider->boot();
            $this->assertSame('Provider view loaded', view('fixture-views::proof')->render());
            $this->assertArrayHasKey($directory, ServiceProvider::pathsToPublish($provider::class, 'cms-views'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_home_redirects_to_the_dashboard_without_an_active_theme(): void
    {
        $this->get('/')->assertRedirect(route('dashboard.index'));
    }

    public function test_an_administrator_can_log_in_without_a_product_key(): void
    {
        $user = UserFactory::new()->superUser()->create(['username' => 'kernadmin']);
        app(ActivateUserService::class)->activate($user);

        $this->post('/admin/login', ['username' => 'kernadmin', 'password' => 'password123'])
            ->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_core_admin_screens_render_without_plugins_or_themes(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());

        foreach (['/admin', '/admin/pages', '/admin/pages/create', '/admin/menus', '/admin/media', '/admin/system', '/admin/settings/general', '/admin/system/users', '/admin/system/roles'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_pages_can_be_created_updated_and_deleted(): void
    {
        config(['core.base.admin.legacy_mode' => true]);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->postJson('/admin/pages/create', ['name' => 'Kern page', 'status' => 'published'])
            ->assertOk()->assertJsonPath('error', false);
        $page = Page::query()->where('name', 'Kern page')->firstOrFail();
        $this->get('/admin/pages', $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('props.pages.total', 1)->assertJsonCount(1, 'props.pages.data');

        $this->postJson('/admin/pages/edit/'.$page->id, ['name' => 'Updated page', 'status' => 'published'])
            ->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'name' => 'Updated page']);

        $this->deleteJson('/admin/pages/'.$page->id)->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_inertia_table_supports_search_ordering_and_pagination(): void
    {
        config(['core.base.admin.legacy_mode' => true]);
        $this->actingAs(UserFactory::new()->superUser()->create());
        foreach (['Alpha Kern', 'Beta Kern', 'Other page'] as $name) {
            $this->postJson(route('pages.store'), ['name' => $name, 'status' => 'published'])
                ->assertOk()->assertJsonPath('error', false);
        }

        $filters = ['page' => 2, 'per_page' => 1, 'search' => 'Kern', 'sort' => 'name', 'direction' => 'desc'];
        $this->get(route('pages.index', $filters), $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('props.pages.total', 2)
            ->assertJsonCount(1, 'props.pages.data')
            ->assertJsonPath('props.pages.data.0.name', 'Alpha Kern');

        $filters['search'] = 'No matching page';
        $this->get(route('pages.index', $filters), $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('props.pages.total', 0)->assertJsonCount(0, 'props.pages.data');
    }

    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
            'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json')),
        ];
    }

    public function test_users_without_page_permission_cannot_create_pages(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->postJson('/admin/pages/create', ['name' => 'Forbidden'])
            ->assertUnauthorized();
        $this->assertDatabaseMissing('pages', ['name' => 'Forbidden']);
    }

    public function test_create_permission_is_applied_to_the_store_route(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.create' => true]]));
        $this->postJson('/admin/pages/create', ['name' => 'Allowed page', 'status' => 'published'])
            ->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('pages', ['name' => 'Allowed page']);
    }

    public function test_page_validation_returns_json_errors(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->postJson('/admin/pages/create', ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
