<?php

namespace Kern\Theme\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Theme\Manager as ManagerSupport;

/**
 * @method static void registerTheme(array|string $theme)
 * @method static array getAllThemes()
 * @method static array getThemes()
 *
 * @see ManagerSupport
 */
class Manager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ManagerSupport::class;
    }
}
