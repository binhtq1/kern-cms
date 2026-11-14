<?php

namespace Kern\Language\Listeners;

use Exception;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Language\Facades\Language;

class UpdatedContentListener
{
    public function handle(UpdatedContentEvent $event): void
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
