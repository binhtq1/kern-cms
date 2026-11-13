<?php

namespace Kern\Language\Listeners;

use Exception;
use Kern\Base\Facades\BaseHelper;
use Kern\Language\Facades\Language;
use Kern\Setting\Models\Setting;
use Kern\Theme\Events\ThemeRemoveEvent;
use Kern\Theme\Facades\ThemeOption;
use Kern\Widget\Models\Widget;

class ThemeRemoveListener
{
    public function handle(ThemeRemoveEvent $event): void
    {
        try {
            $languages = Language::getActiveLanguage(['lang_code']);

            foreach ($languages as $language) {
                Widget::query()
                    ->where(['theme' => Widget::getThemeName($language->lang_code, theme: $event->theme)])
                    ->delete();

                Setting::query()
                    ->where(['key', 'LIKE', ThemeOption::getOptionKey('%', $language->lang_code)])
                    ->delete();
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
