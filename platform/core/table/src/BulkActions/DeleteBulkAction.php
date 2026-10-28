<?php

namespace Kern\Table\BulkActions;

use Illuminate\Database\Eloquent\Model;
use Kern\Base\Contracts\BaseModel;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Exceptions\DisabledInDemoModeException;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Table\Abstracts\TableBulkActionAbstract;

class DeleteBulkAction extends TableBulkActionAbstract
{
    public function __construct()
    {
        $this
            ->label(trans('core/table::table.delete'))
            ->confirmationModalButton(trans('core/table::table.delete'))
            ->beforeDispatch(function (): void {
                if (BaseHelper::hasDemoModeEnabled()) {
                    throw new DisabledInDemoModeException;
                }
            });
    }

    public function dispatch(BaseModel|Model $model, array $ids): BaseHttpResponse
    {
        $model->newQuery()->whereKey($ids)->each(function (BaseModel|Model $item): void {
            $item->delete();

            DeletedContentEvent::dispatch($item::class, request(), $item);
        });

        return BaseHttpResponse::make()
            ->withDeletedSuccessMessage();
    }
}
