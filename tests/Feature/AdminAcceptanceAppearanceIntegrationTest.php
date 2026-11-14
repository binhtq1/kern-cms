<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Kern\ACL\Models\User;
use Kern\Blog\Models\Category;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Kern\Widget\Models\Widget;
use Kern\Widget\Widgets\CoreSimpleMenu;
use Kern\Widget\Widgets\Text;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminAcceptanceAppearanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        $this->actingAs(UserFactory::new()->superUser()->create());

        if (! is_plugin_active('blog')) {
            $this->assertFalse(app(PluginService::class)->activate('blog')['error'], 'blog');
        }

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

    protected function createPublishedPage(string $name, string $slug): Page
    {
        $this->post(route('pages.store'), [
            'name' => $name,
            'content' => '<p>'.$name.' body</p>',
            'status' => 'published',
            'template' => 'default',
            'slug' => $slug,
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('pages.index'));

        return Page::query()->where('name', $name)->firstOrFail();
    }

    public function test_menu_saved_through_admin_renders_location_tree_and_link_target_on_public_theme(): void
    {
        $tree = [[
            'menuItem' => [
                'title' => 'Acceptance parent link',
                'url' => '/acceptance-parent',
                'target' => '_self',
            ],
            'children' => [[
                'menuItem' => [
                    'title' => 'Acceptance child link',
                    'url' => '/acceptance-child',
                    'target' => '_blank',
                ],
            ]],
        ]];

        $this->post(route('menus.store'), [
            'name' => 'Acceptance public menu',
            'status' => 'published',
            'locations' => ['main-menu'],
            'menu_nodes' => json_encode($tree),
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();

        $menu = MenuModel::query()->where('name', 'Acceptance public menu')->firstOrFail();
        $parent = $menu->menuNodes()->where('title', 'Acceptance parent link')->firstOrFail();
        $child = $menu->menuNodes()->where('title', 'Acceptance child link')->firstOrFail();
        $this->assertSame($parent->getKey(), $child->parent_id);
        $this->assertSame(['main-menu'], $menu->locations()->pluck('location')->all());
        $this->assertSame('_blank', $child->target);

        $page = $this->createPublishedPage('Acceptance menu page', 'acceptance-menu-page');
        Menu::load(true);

        $this->get($page->url)->assertOk()
            ->assertSeeInOrder(['Acceptance parent link', 'Acceptance child link'])
            ->assertSee('href="/acceptance-child"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_widget_save_all_persists_and_publicly_renders_available_widget_types(): void
    {
        $category = Category::query()->create([
            'author_type' => User::class,
            'author_id' => auth()->id(),
            'name' => 'Acceptance widget category',
            'status' => 'published',
            'parent_id' => 0,
        ]);

        $this->post(route('posts.store'), [
            'name' => 'Acceptance widget recent post',
            'content' => '<p>Widget post body</p>',
            'status' => 'published',
            'categories' => [$category->getKey()],
            'tag' => json_encode([['value' => 'AcceptanceTag']]),
            'slug' => 'acceptance-widget-recent-post',
            'is_slug_editable' => 1,
            'submitter' => 'save',
        ], $this->headers())->assertRedirect(route('posts.index'));

        $menuTree = [[
            'menuItem' => ['title' => 'Widget custom menu link', 'url' => '/widget-menu-link', 'target' => '_blank'],
        ]];
        $this->post(route('menus.store'), [
            'name' => 'Acceptance widget menu',
            'status' => 'published',
            'menu_nodes' => json_encode($menuTree),
        ], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $menu = MenuModel::query()->where('name', 'Acceptance widget menu')->firstOrFail();

        $widgetTypes = [
            Text::class,
            CoreSimpleMenu::class,
            \BlogCategoriesWidget::class,
            \CustomMenuWidget::class,
            \RecentPostsWidget::class,
            \TagsWidget::class,
        ];
        $items = [
            ['id' => Text::class, 'name' => 'Acceptance text widget', 'content' => '<strong>Widget text rendered</strong>'],
            ['id' => CoreSimpleMenu::class, 'name' => 'Acceptance simple menu widget', 'items' => [[
                ['key' => 'label', 'value' => 'Simple menu link'],
                ['key' => 'url', 'value' => '/simple-menu-link'],
                ['key' => 'attributes', 'value' => ''],
                ['key' => 'is_open_new_tab', 'value' => '0'],
            ]]],
            ['id' => \BlogCategoriesWidget::class, 'name' => 'Acceptance categories widget', 'category_ids' => [(string) $category->getKey()], 'display_posts_count' => 'yes'],
            ['id' => \CustomMenuWidget::class, 'name' => 'Acceptance custom menu widget', 'menu_id' => $menu->slug],
            ['id' => \RecentPostsWidget::class, 'name' => 'Acceptance recent posts widget', 'number_display' => 3, 'type' => 'recent'],
            ['id' => \TagsWidget::class, 'name' => 'Acceptance tags widget', 'number_display' => 5],
        ];

        $this->assertSame($widgetTypes, array_column($items, 'id'));
        $this->post(route('widgets.save_all'), ['groups' => [
            ['sidebar_id' => 'primary_sidebar', 'items' => $items],
        ]], $this->headers())->assertRedirect();

        $storedWidgets = Widget::query()->where('sidebar_id', 'primary_sidebar')->orderBy('position')->get();
        $this->assertSame($widgetTypes, $storedWidgets->pluck('widget_id')->all());
        $this->assertSame('Widget text rendered', trim(strip_tags($storedWidgets[0]->data['content'])));

        $page = $this->createPublishedPage('Acceptance widget page', 'acceptance-widget-page');
        $this->get($page->url)->assertOk()
            ->assertSee('Widget text rendered')
            ->assertSee('Simple menu link')
            ->assertSee('Acceptance widget category')
            ->assertSee('Acceptance widget recent post')
            ->assertSee('AcceptanceTag')
            ->assertSee('Widget custom menu link');
    }
}
