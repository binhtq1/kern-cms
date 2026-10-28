<?php

namespace Kern\Base\Listeners;

use Exception;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Facades\BaseHelper;

class CreatedContentListener
{
    public function handle(CreatedContentEvent $event): void
    {
        try {
            do_action(BASE_ACTION_AFTER_CREATE_CONTENT, $event->screen, $event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
