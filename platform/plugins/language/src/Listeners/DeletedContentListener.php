<?php

namespace Kern\Language\Listeners;

use Exception;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Language\Facades\Language;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        try {
            Language::deleteLanguage($event->screen, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
