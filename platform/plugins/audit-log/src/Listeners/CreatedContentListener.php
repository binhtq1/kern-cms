<?php

namespace Kern\AuditLog\Listeners;

use Exception;
use Illuminate\Support\Str;
use Kern\AuditLog\AuditLog;
use Kern\AuditLog\Events\AuditHandlerEvent;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Facades\BaseHelper;

class CreatedContentListener
{
    public function handle(CreatedContentEvent $event): void
    {
        try {
            if ($event->data->getKey()) {
                $model = $event->screen;

                if ($model === 'form') {
                    $model = strtolower(Str::afterLast(get_class($event->data), '\\'));
                }

                event(new AuditHandlerEvent(
                    $model,
                    'created',
                    $event->data->getKey(),
                    AuditLog::getReferenceName($model, $event->data),
                    'info'
                ));
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
