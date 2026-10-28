<?php

namespace Kern\Base\Listeners;

use Exception;
use Kern\Base\Events\BeforeEditContentEvent;
use Kern\Base\Facades\BaseHelper;

class BeforeEditContentListener
{
    public function handle(BeforeEditContentEvent $event): void
    {
        try {
            do_action(BASE_ACTION_BEFORE_EDIT_CONTENT, $event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
