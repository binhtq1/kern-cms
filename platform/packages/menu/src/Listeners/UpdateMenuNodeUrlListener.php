<?php

namespace Kern\Menu\Listeners;

use Exception;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Models\BaseModel;
use Kern\Base\Supports\RepositoryHelper;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Models\MenuNode;
use Kern\Slug\Events\UpdatedSlugEvent;

class UpdateMenuNodeUrlListener
{
    public function handle(UpdatedSlugEvent $event): void
    {
        if (
            ! $event->data instanceof BaseModel ||
            ! in_array($event->data::class, Menu::getMenuOptionModels())
        ) {
            return;
        }

        try {
            $query = MenuNode::query()
                ->where([
                    'reference_id' => $event->data->getKey(),
                    'reference_type' => $event->data::class,
                ]);

            $nodes = RepositoryHelper::applyBeforeExecuteQuery($query, $event->data)->get();

            foreach ($nodes as $node) {
                $newUrl = str_replace(url(''), '', $node->reference->url);
                if ($node->url != $newUrl) {
                    $node->url = $newUrl;
                    $node->save();
                }
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
