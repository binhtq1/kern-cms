<?php

namespace Kern\Widget\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Widget\Factories\WidgetFactory;
use Kern\Widget\WidgetGroup;

/**
 * @method static \Kern\Widget\Factories\WidgetFactory registerWidget(string $widget)
 * @method static array getWidgets()
 * @method static \Illuminate\Support\HtmlString|string|null run()
 *
 * @see WidgetFactory
 */
class Widget extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'kern.widget';
    }

    public static function group(string $name): WidgetGroup
    {
        return app('kern.widget-group-collection')->group($name);
    }
}
