<?php

namespace Kern\Widget\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Kern\Base\Models\BaseModel;
use Kern\Language\Facades\Language;
use Kern\Theme\Facades\Theme;

class Widget extends BaseModel
{
    protected $table = 'widgets';

    protected $fillable = [
        'widget_id',
        'sidebar_id',
        'theme',
        'position',
        'data',
    ];

    protected $casts = [
        'data' => 'json',
    ];

    protected function position(): Attribute
    {
        return Attribute::get(fn ($value) => $value >= 0 && $value < 127 ? $value : (int) substr($value, -1));
    }

    public static function getThemeName(
        ?string $locale = null,
        ?string $defaultLocale = null,
        ?string $theme = null
    ): string {
        if (! $theme) {
            $theme = Theme::getThemeName();
        }

        if (! is_plugin_active('language')) {
            return $theme;
        }

        if ($refLang = Language::getRefLang()) {
            $locale = $refLang;
        }

        if (! $defaultLocale) {
            $defaultLocale = Language::getDefaultLocale();
        }

        return (! $locale || $locale == $defaultLocale) ? $theme : ($theme.'-'.ltrim($locale, '-'));
    }
}
