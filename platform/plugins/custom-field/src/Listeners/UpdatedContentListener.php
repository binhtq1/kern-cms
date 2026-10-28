<?php

namespace Kern\CustomField\Listeners;

use Exception;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\CustomField\Facades\CustomField;

class UpdatedContentListener
{
    public function handle(UpdatedContentEvent $event): void
    {
        try {
            CustomField::saveCustomFields($event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
