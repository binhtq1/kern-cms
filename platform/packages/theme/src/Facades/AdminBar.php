<?php

namespace Kern\Theme\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Theme\Supports\AdminBar as AdminBarSupport;

/**
 * @method static bool isDisplay()
 * @method static \Kern\Theme\Supports\AdminBar setIsDisplay(bool $isDisplay = true)
 * @method static array getGroups()
 * @method static array getLinksNoGroup()
 * @method static \Kern\Theme\Supports\AdminBar setLinksNoGroup(array $links)
 * @method static \Kern\Theme\Supports\AdminBar registerGroup(string $slug, string $title, string $link = 'javascript:;')
 * @method static \Kern\Theme\Supports\AdminBar registerLink(string $title, string $url, $group = null, string|null $permission = null)
 * @method static string render()
 *
 * @see AdminBarSupport
 */
class AdminBar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AdminBarSupport::class;
    }
}
