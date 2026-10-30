<?php

namespace Kern\AuditLog\Listeners;

use Exception;
use Kern\AuditLog\AuditLog;
use Kern\AuditLog\Events\AuditHandlerEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Facades\BaseHelper;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        try {
            if ($event->data->getKey()) {
                $model = $event->screen;

                event(new AuditHandlerEvent(
                    $model,
                    'deleted',
                    $event->data->getKey(),
                    AuditLog::getReferenceName($model, $event->data),
                    'danger'
                ));
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
