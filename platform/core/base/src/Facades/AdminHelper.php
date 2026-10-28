<?php

namespace Kern\Base\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Base\Helpers\AdminHelper as AdminHelperSupport;

/**
 * @method static \Illuminate\Routing\RouteRegistrar registerRoutes(\Closure|callable $closure, array $middleware = ['web','core','auth'])
 * @method static bool isInAdmin(bool $force = false)
 * @method static string themeMode()
 * @method static bool isPreviewing()
 *
 * @see AdminHelperSupport
 */
class AdminHelper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AdminHelperSupport::class;
    }
}
