<?php

namespace Kern\SeoHelper\Listeners;

use Exception;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\SeoHelper\Facades\SeoHelper;

class CreatedContentListener
{
    public function handle(CreatedContentEvent $event): void
    {
        try {
            SeoHelper::saveMetaData($event->screen, $event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
