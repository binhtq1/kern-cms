<?php

namespace Kern\Slug\Listeners;

use Kern\Base\Contracts\BaseModel;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Slug\Facades\SlugHelper;
use Kern\Slug\Models\Slug;

class DeletedContentListener
{
    public function handle(DeletedContentEvent $event): void
    {
        if ($event->data instanceof BaseModel && SlugHelper::isSupportedModel($event->data::class)) {
            Slug::query()->where([
                'reference_id' => $event->data->getKey(),
                'reference_type' => $event->data::class,
            ])->delete();
        }
    }
}
