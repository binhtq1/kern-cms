<?php

namespace Kern\Language\Listeners;

use Exception;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Language\Facades\Language;

class CreatedContentListener
{
    public function handle(CreatedContentEvent $event): void
    {
        try {
            if ($event->request->input('language')) {
                Language::saveLanguage($event->screen, $event->request, $event->data);
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
