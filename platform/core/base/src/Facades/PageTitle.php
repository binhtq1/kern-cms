<?php

namespace Kern\Base\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Base\Supports\PageTitle as PageTitleSupport;

/**
 * @method static void setSiteName(string $siteName)
 * @method static void setSeparator(string $separator)
 * @method static void setTitle(string $title)
 * @method static string|null getTitle(bool $withSiteName = true)
 *
 * @see PageTitleSupport
 */
class PageTitle extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PageTitleSupport::class;
    }
}
