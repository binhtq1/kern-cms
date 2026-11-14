<?php

namespace Kern\Gallery\Listeners;

use Exception;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Gallery\Facades\Gallery;

class CreatedContentListener
{
    public function handle(CreatedContentEvent $event): void
    {
        try {
            Gallery::saveGallery($event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
