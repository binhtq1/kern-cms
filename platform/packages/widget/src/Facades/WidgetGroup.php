<?php

namespace Kern\Widget\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Widget\WidgetGroupCollection;

/**
 * @method static \Kern\Widget\WidgetGroup group(string $sidebarId)
 * @method static \Kern\Widget\WidgetGroupCollection setGroup(array $args)
 * @method static \Kern\Widget\WidgetGroupCollection removeGroup(string $groupId)
 * @method static array getGroups()
 * @method static string render(string $sidebarId)
 * @method static void load(bool $force = false)
 * @method static \Illuminate\Support\Collection getData()
 *
 * @see WidgetGroupCollection
 */
class WidgetGroup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'kern.widget-group-collection';
    }
}
