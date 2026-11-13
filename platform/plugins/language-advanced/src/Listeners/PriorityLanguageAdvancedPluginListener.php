<?php

namespace Kern\LanguageAdvanced\Listeners;

use Exception;
use Kern\Base\Facades\BaseHelper;
use Kern\LanguageAdvanced\Plugin;

class PriorityLanguageAdvancedPluginListener
{
    public function handle(): void
    {
        try {
            Plugin::activated();
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
