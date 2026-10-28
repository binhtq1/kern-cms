<?php

namespace Kern\SeoHelper\Listeners;

use Exception;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\SeoHelper\Facades\SeoHelper;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        try {
            SeoHelper::deleteMetaData($event->screen, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
