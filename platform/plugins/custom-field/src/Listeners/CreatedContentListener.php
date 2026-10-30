<?php

namespace Kern\CustomField\Listeners;

use Exception;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\CustomField\Facades\CustomField;

class CreatedContentListener
{
    public function handle(CreatedContentEvent $event): void
    {
        try {
            CustomField::saveCustomFields($event->request, $event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
