<?php

namespace Kern\CustomField\Listeners;

use Exception;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\CustomField\Facades\CustomField;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        try {
            CustomField::deleteCustomFields($event->data);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
