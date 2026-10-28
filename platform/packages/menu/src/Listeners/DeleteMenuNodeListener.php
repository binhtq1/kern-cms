<?php

namespace Kern\Menu\Listeners;

use Kern\Base\Contracts\BaseModel;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Models\MenuNode;

class DeleteMenuNodeListener
{
    public function handle(DeletedContentEvent $event): void
    {
        if (
            ! $event->data instanceof BaseModel ||
            ! in_array($event->data::class, Menu::getMenuOptionModels())
        ) {
            return;
        }

        MenuNode::query()
            ->where([
                'reference_id' => $event->data->getKey(),
                'reference_type' => $event->data::class,
            ])
            ->delete();
    }
}
