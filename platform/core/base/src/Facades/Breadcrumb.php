<?php

namespace Kern\Base\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Base\Supports\Breadcrumb as BreadcrumbSupport;

/**
 * @method static \Kern\Base\Supports\Breadcrumb for(string $group)
 * @method static \Kern\Base\Supports\Breadcrumb default()
 * @method static static add(string $label, string $url = '')
 * @method static static prepend(string $label, string $url = '')
 * @method static \Illuminate\Support\Collection getItems()
 * @method static string render()
 * @method static string toHtml()
 * @method static static renderUsing(\Closure $renderUsingCallback)
 * @method static static beforeRendering(\Closure $beforeRenderCallback)
 * @method static static afterRendering(\Closure $afterRenderCallback)
 * @method static mixed|null rendering(\Closure|string $content)
 *
 * @see BreadcrumbSupport
 */
class Breadcrumb extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BreadcrumbSupport::class;
    }
}
