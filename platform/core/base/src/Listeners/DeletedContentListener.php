<?php

namespace Kern\Base\Listeners;

use Exception;
use Kern\Base\Contracts\BaseModel;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Models\MetaBox;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        if (! $event->data instanceof BaseModel) {
            return;
        }

        try {
            do_action(BASE_ACTION_AFTER_DELETE_CONTENT, $event->screen, $event->request, $event->data);

            MetaBox::query()->where([
                'reference_id' => $event->data->getKey(),
                'reference_type' => $event->data::class,
            ])->delete();
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
