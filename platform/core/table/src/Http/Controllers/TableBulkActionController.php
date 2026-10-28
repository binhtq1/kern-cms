<?php

namespace Kern\Table\Http\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Http\Requests\DispatchBulkActionRequest;

class TableBulkActionController extends TableController
{
    public function __invoke(DispatchBulkActionRequest $request): BaseHttpResponse
    {
        if (
            ! class_exists($request->input('bulk_action_table'))
            || ! class_exists($request->input('bulk_action_target'))
        ) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('core/table::invalid_bulk_action'));
        }

        try {
            /**
             * @var TableAbstract $table
             */
            $table = app()->make($request->input('bulk_action_table'));

            abort_unless($table instanceof TableAbstract, 400);

            return $table->dispatchBulkAction();
        } catch (BindingResolutionException) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(__('Something went wrong.'));
        }
    }
}
