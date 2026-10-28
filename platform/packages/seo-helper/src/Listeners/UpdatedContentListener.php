<?php

namespace Kern\SeoHelper\Listeners;

use Exception;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\SeoHelper\Facades\SeoHelper;

class UpdatedContentListener
{
    public function handle(UpdatedContentEvent $event): void
    {
        try {
            SeoHelper::saveMetaData($event->screen, $event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
