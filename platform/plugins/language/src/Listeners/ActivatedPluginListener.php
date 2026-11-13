<?php

namespace Kern\Language\Listeners;

use Exception;
use Kern\Base\Facades\BaseHelper;
use Kern\Language\Plugin;

class ActivatedPluginListener
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
