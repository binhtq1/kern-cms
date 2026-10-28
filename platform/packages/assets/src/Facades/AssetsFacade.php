<?php

namespace Kern\Assets\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Assets\Assets;

/**
 * @since 22/07/2015 11:25 PM
 */
class AssetsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Assets::class;
    }
}
