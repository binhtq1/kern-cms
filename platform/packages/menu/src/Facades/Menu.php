<?php

namespace Kern\Menu\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Menu\Menu as BaseMenu;

/**
 * @method static bool hasMenu(string $slug)
 * @method static array recursiveSaveMenu(array $menuNodes, string|int $menuId, string|int $parentId)
 * @method static \Kern\Menu\Models\MenuNode getReferenceMenuNode(array $item, \Kern\Menu\Models\MenuNode $menuNode)
 * @method static \Kern\Menu\Menu addMenuLocation(string $location, string $description)
 * @method static array getMenuLocations()
 * @method static \Kern\Menu\Menu removeMenuLocation(string $location)
 * @method static string|null renderMenuLocation(string $location, array $attributes = [])
 * @method static bool isLocationHasMenu(string $location)
 * @method static void load(bool $force = false)
 * @method static string|null generateMenu(array $args = [])
 * @method static void registerMenuOptions(string $model, string $name)
 * @method static string|null generateSelect(array $args = [])
 * @method static \Kern\Menu\Menu addMenuOptionModel(string $model)
 * @method static array getMenuOptionModels()
 * @method static \Kern\Menu\Menu setMenuOptionModels(array $models)
 * @method static \Kern\Menu\Menu clearCacheMenuItems()
 * @method static void useMenuItemIconImage()
 * @method static void saveMenuNodeImages(array $nodes, \Kern\Menu\Models\MenuNode $model)
 * @method static void useMenuItemBadge()
 * @method static void saveMenuNodeBadges(array $nodes, \Kern\Menu\Models\MenuNode $model)
 *
 * @see BaseMenu
 */
class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseMenu::class;
    }
}
