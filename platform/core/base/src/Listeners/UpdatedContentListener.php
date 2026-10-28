<?php

namespace Kern\Base\Listeners;

use Exception;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\BaseHelper;

class UpdatedContentListener
{
    public function handle(UpdatedContentEvent $event): void
    {
        try {
            do_action(BASE_ACTION_AFTER_UPDATE_CONTENT, $event->screen, $event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
