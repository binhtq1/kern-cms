<?php

namespace Kern\Gallery\Listeners;

use Exception;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Gallery\Facades\Gallery;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        try {
            Gallery::deleteGallery($event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
