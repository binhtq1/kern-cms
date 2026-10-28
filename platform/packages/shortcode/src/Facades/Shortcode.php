<?php

namespace Kern\Shortcode\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Kern\Shortcode\Shortcode register(string $key, string|null $name, string|null $description = null, $callback = null, string $previewImage = '')
 * @method static void remove(string $key)
 * @method static \Kern\Shortcode\Shortcode setPreviewImage(string $key, string $previewImage)
 * @method static \Kern\Shortcode\Shortcode enable()
 * @method static \Kern\Shortcode\Shortcode disable()
 * @method static \Illuminate\Support\HtmlString compile(string $value, bool $force = false)
 * @method static string|null strip(string|null $value)
 * @method static array getAll()
 * @method static void setAdminConfig(string $key, callable|array|string|null $html)
 * @method static void modifyAdminConfig(string $key, callable $callback)
 * @method static string generateShortcode(string $name, array $attributes = [], string|null $content = null, bool $lazy = false)
 * @method static \Kern\Shortcode\Compilers\ShortcodeCompiler getCompiler()
 * @method static \Kern\Shortcode\ShortcodeField fields()
 *
 * @see \Kern\Shortcode\Shortcode
 */
class Shortcode extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shortcode';
    }
}
