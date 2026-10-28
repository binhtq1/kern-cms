<?php

namespace Kern\Language;

use Illuminate\Support\Facades\Schema;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;
use Kern\Setting\Facades\Setting;

class Plugin extends PluginOperationAbstract
{
    public static function activated(): void
    {
        $plugins = get_active_plugins();

        if (($key = array_search('language', $plugins)) !== false) {
            unset($plugins[$key]);
        }

        array_unshift($plugins, 'language');

        Setting::forceSet('activated_plugins', json_encode($plugins))->save();
    }

    public static function remove(): void
    {
        Schema::dropIfExists('languages');
        Schema::dropIfExists('language_meta');

        Setting::delete([
            'language_hide_default',
            'language_switcher_display',
            'language_display',
            'language_hide_languages',
        ]);
    }
}
