<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Kern\Base\Forms\Fields\TextField;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Forms\MenuForm;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Menu\Models\MenuNode;
use Kern\Widget\Events\RenderingWidgetSettings;
use Kern\Widget\Models\Widget;
use Kern\Widget\Widgets\CoreSimpleMenu;
use Kern\Widget\Widgets\Text;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminMenuWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.menus' => 'inertia', 'core.base.admin.modules.widgets' => 'inertia']);
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_menu_form_exposes_saved_target_identity_only_after_creation(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('menus.create'), $this->headers())->assertOk()->assertJsonPath('props.menu.id', null);
        $menu = MenuModel::query()->create(['name' => 'Saved identity', 'status' => 'published']);
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.id', $menu->getKey());
    }

    public function test_menu_and_widget_screens_require_permissions_and_ignore_legacy_module_flags(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('menus.index'), $this->headers())->assertForbidden();
        $this->get(route('widgets.index'), $this->headers())->assertForbidden();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('menus.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Menu/Index');
        $this->get(route('menus.create'), $this->headers())->assertOk()->assertJsonPath('component', 'Menu/Form');
        $this->get(route('widgets.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Widgets/Index');
        config(['core.base.admin.modules.menus' => 'blade', 'core.base.admin.modules.widgets' => 'blade']);
        $this->get(route('menus.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Menu/Index');
        $this->get(route('widgets.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Widgets/Index');
        $this->get(route('menus.get-node'))->assertMethodNotAllowed();
    }

    public function test_menu_tree_saves_nested_nodes_locations_and_deletions(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        Menu::addMenuLocation('footer-test', 'Footer');
        $tree = [['menuItem' => ['title' => 'Parent', 'url' => '/parent', 'target' => '_self'], 'children' => [['menuItem' => ['title' => 'Child', 'url' => '/child', 'target' => '_blank']]]]];
        $this->post(route('menus.store'), ['name' => 'Footer navigation', 'status' => 'published', 'locations' => ['footer-test'], 'menu_nodes' => json_encode($tree)], $this->headers())->assertRedirect();
        $menu = MenuModel::query()->where('name', 'Footer navigation')->firstOrFail();
        $this->get(route('menus.index', ['search' => 'fOoTeR']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 1);
        $parent = $menu->menuNodes()->where('title', 'Parent')->firstOrFail();
        $child = $menu->menuNodes()->where('title', 'Child')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue((bool) $parent->has_child);
        $this->assertSame(['footer-test'], $menu->locations()->pluck('location')->all());
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.nodes.0.title', 'Parent');
        $this->post(route('menus.update', $menu), ['name' => 'Updated navigation', 'status' => 'draft', 'locations' => [], 'deleted_nodes' => (string) $child->id, 'menu_nodes' => json_encode([['menuItem' => ['id' => $parent->id, 'title' => 'Parent', 'url' => '/parent']]])], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('menu_nodes', ['id' => $child->id]);
        $this->assertFalse((bool) $parent->fresh()->has_child);
        $this->assertSame(0, $menu->locations()->count());
    }

    public function test_invalid_or_foreign_tree_cannot_change_menu_or_delete_existing_nodes(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $menu = MenuModel::query()->create(['name' => 'Original navigation', 'status' => 'published']);
        $other = MenuModel::query()->create(['name' => 'Other navigation', 'status' => 'published']);
        $node = MenuNode::query()->create(['menu_id' => $menu->id, 'title' => 'Keep', 'url' => '/keep']);
        $foreign = MenuNode::query()->create(['menu_id' => $other->id, 'title' => 'Foreign', 'url' => '/foreign']);
        $payload = ['name' => 'Changed navigation', 'status' => 'published', 'deleted_nodes' => (string) $node->id, 'menu_nodes' => json_encode([['menuItem' => ['id' => $foreign->id, 'title' => 'Stolen', 'url' => '/stolen']]])];
        $this->post(route('menus.update', $menu), $payload, $this->headers())->assertSessionHasErrors('menu_nodes');
        $this->assertSame('Original navigation', $menu->fresh()->name);
        $this->assertDatabaseHas('menu_nodes', ['id' => $node->id]);
        $payload['menu_nodes'] = json_encode([['menuItem' => ['title' => 'Unsafe', 'url' => 'javascript:alert(1)']]]);
        $this->postJson(route('menus.update', $menu), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('menu_nodes', ['id' => $node->id]);
        $payload['menu_nodes'] = '{broken';
        $this->postJson(route('menus.update', $menu), $payload)->assertUnprocessable();
    }

    public function test_widget_save_all_registers_theme_areas_before_validation(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        app('events')->listen(RenderingWidgetSettings::class, function (): void {
            register_sidebar(['id' => 'theme-footer', 'name' => 'Theme footer']);
        });
        $this->postJson(route('widgets.save_all'), ['groups' => [
            ['sidebar_id' => 'primary_sidebar', 'items' => []],
            ['sidebar_id' => 'theme-footer', 'items' => [['id' => Text::class, 'name' => 'Theme widget', 'content' => 'Saved in the footer']]],
        ]])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('widgets', ['sidebar_id' => 'theme-footer', 'widget_id' => Text::class]);
        $this->assertSame('Saved in the footer', Widget::query()->where('sidebar_id', 'theme-footer')->firstOrFail()->data['content']);
    }

    public function test_legacy_widget_update_registers_theme_areas_before_validation(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        app('events')->listen(RenderingWidgetSettings::class, function (): void {
            register_sidebar(['id' => 'theme-footer', 'name' => 'Theme footer']);
        });
        $this->postJson(route('widgets.save_widgets_sidebar'), ['sidebar_id' => 'theme-footer', 'items' => [http_build_query(['id' => Text::class, 'name' => 'Legacy widget', 'content' => 'Blade compatible'])]])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('widgets', ['sidebar_id' => 'theme-footer', 'widget_id' => Text::class]);
    }

    public function test_widgets_accept_legacy_encoded_data_and_react_atomic_area_moves(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        register_sidebar(['id' => 'footer-test', 'name' => 'Footer']);
        $this->postJson(route('widgets.save_widgets_sidebar'), ['sidebar_id' => 'primary_sidebar', 'items' => [http_build_query(['id' => Text::class, 'name' => 'Existing title', 'content' => '<p>Exact content</p>'])]])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('widgets', ['sidebar_id' => 'primary_sidebar', 'widget_id' => Text::class]);
        $this->post(route('widgets.save_all'), ['groups' => [
            ['sidebar_id' => 'primary_sidebar', 'items' => []],
            ['sidebar_id' => 'footer-test', 'items' => [['id' => Text::class, 'name' => 'Moved title', 'content' => '<p>Exact content</p>']]],
        ]], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('widgets', ['sidebar_id' => 'primary_sidebar']);
        $widget = Widget::query()->where('sidebar_id', 'footer-test')->firstOrFail();
        $this->assertSame('<p>Exact content</p>', $widget->data['content']);
        $this->assertSame(0, $widget->position);
    }

    public function test_widget_validation_preserves_all_areas_on_unknown_type_and_invalid_configuration(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $widget = Widget::query()->create(['theme' => Widget::getThemeName(), 'sidebar_id' => 'primary_sidebar', 'widget_id' => Text::class, 'position' => 0, 'data' => ['id' => Text::class, 'name' => 'Keep', 'content' => 'Original']]);
        $this->postJson(route('widgets.save_widgets_sidebar'), ['sidebar_id' => 'primary_sidebar', 'items' => [['id' => 'NotRegisteredWidget']]])->assertUnprocessable();
        $this->postJson(route('widgets.save_widgets_sidebar'), ['sidebar_id' => 'unknown-area', 'items' => []])->assertUnprocessable();
        $this->postJson(route('widgets.save_all'), ['groups' => [['sidebar_id' => 'primary_sidebar', 'items' => [['id' => Text::class, 'name' => ['bad'], 'content' => 'Changed']]]]])->assertUnprocessable();
        $this->assertSame('Original', $widget->fresh()->data['content']);
        $this->assertSame(1, Widget::query()->count());
    }

    public function test_menu_persistence_failure_rolls_back_name_locations_deletions_and_prior_nodes(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $menu = MenuModel::query()->create(['name' => 'Original navigation', 'status' => 'published']);
        $keep = MenuNode::query()->create(['menu_id' => $menu->id, 'title' => 'Keep', 'url' => '/keep']);
        Exceptions::fake();
        MenuNode::creating(function (MenuNode $node): void {
            if ($node->title === 'Fail') {
                throw new RuntimeException('Simulated menu persistence failure');
            }
        });
        $tree = [['menuItem' => ['title' => 'First', 'url' => '/first']], ['menuItem' => ['title' => 'Fail', 'url' => '/fail']]];
        $this->postJson(route('menus.update', $menu), ['name' => 'Changed navigation', 'status' => 'draft', 'deleted_nodes' => (string) $keep->id, 'menu_nodes' => json_encode($tree)])->assertStatus(500);
        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame('Original navigation', $menu->fresh()->name);
        $this->assertDatabaseHas('menu_nodes', ['id' => $keep->id]);
        $this->assertDatabaseMissing('menu_nodes', ['title' => 'First']);
    }

    public function test_widget_persistence_failure_rolls_back_every_area_in_the_batch(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        register_sidebar(['id' => 'footer-test', 'name' => 'Footer']);
        $widget = Widget::query()->create(['theme' => Widget::getThemeName(), 'sidebar_id' => 'primary_sidebar', 'widget_id' => Text::class, 'position' => 0, 'data' => ['id' => Text::class, 'name' => 'Original', 'content' => 'Keep']]);
        Exceptions::fake();
        Widget::creating(function (Widget $widget): void {
            if (($widget->data['name'] ?? '') === 'Fail') {
                throw new RuntimeException('Simulated widget persistence failure');
            }
        });
        $this->postJson(route('widgets.save_all'), ['groups' => [
            ['sidebar_id' => 'primary_sidebar', 'items' => []],
            ['sidebar_id' => 'footer-test', 'items' => [['id' => Text::class, 'name' => 'Fail', 'content' => 'Changed']]],
        ]])->assertStatus(500);
        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame('Keep', $widget->fresh()->data['content']);
        $this->assertDatabaseMissing('widgets', ['sidebar_id' => 'footer-test']);
    }

    public function test_unknown_menu_extension_is_blocked_after_running_pre_render_hooks(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        MenuForm::beforeRendering(fn (MenuForm $form) => $form->add('extension_field', TextField::class));
        $this->get(route('menus.create'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_menu_bulk_actions_enforce_permissions_and_preserve_tree_cleanup(): void
    {
        $menu = MenuModel::query()->create(['name' => 'Bulk navigation', 'status' => 'published']);
        MenuNode::query()->create(['menu_id' => $menu->id, 'title' => 'Child', 'url' => '/child']);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['menus.index' => true]]));
        $this->post(route('menus.bulk'), ['ids' => [$menu->id], 'action' => 'delete'], $this->headers())->assertForbidden();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->post(route('menus.bulk'), ['ids' => [$menu->id], 'action' => 'status', 'status' => 'draft'], $this->headers())->assertRedirect();
        $this->assertSame('draft', $menu->fresh()->status->getValue());
        $this->post(route('menus.bulk'), ['ids' => [$menu->id], 'action' => 'delete'], $this->headers())->assertRedirect();
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertDatabaseMissing('menu_nodes', ['menu_id' => $menu->id]);
    }

    public function test_simple_menu_widget_preserves_repeater_shape_and_rejects_unsafe_links(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $link = [['key' => 'label', 'value' => 'About'], ['key' => 'url', 'value' => '/about'], ['key' => 'attributes', 'value' => 'rel="nofollow" aria-label="About"'], ['key' => 'is_open_new_tab', 'value' => '1']];
        $payload = ['sidebar_id' => 'primary_sidebar', 'items' => [['id' => CoreSimpleMenu::class, 'name' => 'Footer links', 'items' => [$link]]]];
        $this->postJson(route('widgets.save_widgets_sidebar'), $payload)->assertOk()->assertJsonPath('error', false);
        $widget = Widget::query()->firstOrFail();
        $this->assertSame($link, $widget->data['items'][0]);
        $payload['items'][0]['items'][0][1]['value'] = 'javascript:alert(1)';
        $this->postJson(route('widgets.save_widgets_sidebar'), $payload)->assertUnprocessable();
        $payload['items'][0]['items'][0][1]['value'] = '/about';
        $payload['items'][0]['items'][0][2]['value'] = 'onclick="alert(1)"';
        $this->postJson(route('widgets.save_widgets_sidebar'), $payload)->assertUnprocessable();
        $this->assertSame($link, $widget->fresh()->data['items'][0]);
    }

    public function test_ripple_menu_icon_images_render_and_roundtrip_for_new_and_existing_nodes(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        Menu::useMenuItemIconImage();

        $this->get(route('menus.create'), $this->headers())->assertOk()->assertJsonPath('component', 'Menu/Form')->assertJsonPath('props.iconImageEnabled', true);
        $this->post(route('menus.store'), ['name' => 'Image navigation', 'status' => 'published', 'menu_nodes' => json_encode([['menuItem' => ['title' => 'With image', 'url' => '/about', 'icon_image' => 'icons/about.png']]])], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $menu = MenuModel::query()->where('name', 'Image navigation')->firstOrFail();
        $node = $menu->menuNodes()->firstOrFail();
        $this->assertSame('icons/about.png', $node->getMetaData('icon_image', true));
        $this->get(route('menus.edit', $menu), $this->headers())->assertOk()->assertJsonPath('props.menu.nodes.0.icon_image', 'icons/about.png');
        $this->post(route('menus.update', $menu), ['name' => 'Image navigation', 'status' => 'published', 'menu_nodes' => json_encode([['menuItem' => ['id' => $node->id, 'title' => 'With image', 'url' => '/about', 'icon_image' => '']]])], $this->headers())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEmpty($node->fresh()->getMetaData('icon_image', true));
    }

    public function test_menu_registered_bulk_deletion_rolls_back_nodes_and_keeps_permission_guards(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        $first = MenuModel::query()->create(['name' => 'First selection', 'status' => 'published']);
        $blocked = MenuModel::query()->create(['name' => 'Blocked selection', 'status' => 'published']);
        $node = $first->menuNodes()->create(['title' => 'Keep after rollback', 'url' => '/safe', 'position' => 0]);
        MenuModel::deleting(fn (MenuModel $model) => $model->is($blocked) ? false : null);
        $this->post(route('menus.bulk'), ['ids' => [$first->getKey(), $blocked->getKey()], 'action' => 'delete'], $this->headers())->assertSessionHasErrors('ids');
        $this->assertModelExists($first);
        $this->assertModelExists($blocked);
        $this->assertModelExists($node);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['menus.index' => true]]));
        $this->post(route('menus.bulk'), ['ids' => [$first->getKey()], 'action' => 'change', 'field' => 'name', 'value' => 'Forbidden rename'], $this->headers())->assertForbidden();
        $this->assertSame('First selection', $first->fresh()->name);
    }
}
