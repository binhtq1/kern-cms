<?php

namespace Kern\Theme\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Theme\Supports\SiteMapManager as SiteMapManagerSupport;

/**
 * @method static \Kern\Theme\Supports\SiteMapManager init(string|null $prefix = null, string $extension = 'xml')
 * @method static \Kern\Theme\Supports\SiteMapManager addSitemap(string $loc, string|null $lastModified = null)
 * @method static string route(string|null $key = null)
 * @method static \Kern\Theme\Supports\SiteMapManager add(string $url, string|null $date = null, string $priority = '1.0', string $sequence = 'daily')
 * @method static bool isCached()
 * @method static \Kern\Sitemap\Sitemap getSiteMap()
 * @method static \Illuminate\Http\Response render(string $type = 'xml')
 * @method static array getKeys()
 * @method static \Kern\Theme\Supports\SiteMapManager registerKey(array|string $key, string|null $value = null)
 * @method static \Kern\Theme\Supports\SiteMapManager removeKey(array|string $key)
 * @method static array allowedExtensions()
 *
 * @see SiteMapManagerSupport
 */
class SiteMapManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SiteMapManagerSupport::class;
    }
}
