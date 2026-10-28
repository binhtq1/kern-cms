<?php

namespace Kern\Base\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Base\Supports\Assets as BaseAssets;

/**
 * @method static void setConfig(array $config)
 * @method static string renderHeader($lastStyles = [])
 * @method static string renderFooter()
 * @method static \Kern\Base\Supports\Assets usingVueJS()
 * @method static \Kern\Base\Supports\Assets disableVueJS()
 * @method static bool hasVueJs()
 * @method static static addScripts(array|string $assets)
 * @method static static addStyles(array|string $assets)
 * @method static static addStylesDirectly(array|string $assets, array $attributes = [])
 * @method static static addScriptsDirectly(array|string $assets, string $location = 'footer', array $attributes = [])
 * @method static static removeStyles(array|string $assets)
 * @method static static removeScripts(array|string $assets)
 * @method static static removeItemDirectly(array|string $assets, string|null $location = null)
 * @method static array getScripts(string|null $location = null)
 * @method static array getStyles(array $lastStyles = [])
 * @method static string|null scriptToHtml(string $name)
 * @method static string|null styleToHtml(string $name)
 * @method static string getBuildVersion()
 * @method static \Kern\Assets\HtmlBuilder getHtmlBuilder()
 *
 * @see BaseAssets
 */
class Assets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseAssets::class;
    }
}
