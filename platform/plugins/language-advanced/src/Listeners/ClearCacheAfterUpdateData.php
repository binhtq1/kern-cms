<?php

namespace Kern\LanguageAdvanced\Listeners;

use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Models\BaseModel;
use Kern\Support\Services\Cache\Cache;

class ClearCacheAfterUpdateData
{
    public function handle(UpdatedContentEvent $event): void
    {
        if (! $event->data instanceof BaseModel) {
            return;
        }

        Cache::make($event->data::class)->flush();
    }
}
