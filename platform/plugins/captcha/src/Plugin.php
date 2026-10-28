<?php

namespace Kern\Captcha;

use Kern\PluginManagement\Abstracts\PluginOperationAbstract;
use Kern\Setting\Facades\Setting;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Setting::delete([
            'enable_captcha',
            'captcha_type',
            'captcha_hide_badge',
            'captcha_site_key',
            'captcha_secret',
        ]);
    }
}
