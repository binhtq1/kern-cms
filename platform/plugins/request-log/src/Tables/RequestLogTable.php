<?php

namespace Kern\RequestLog\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\RequestLog\Models\RequestLog;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\Columns\Column;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\LinkableColumn;
use Kern\Table\HeaderActions\HeaderAction;

class RequestLogTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(RequestLog::class)
            ->addColumns([
                IdColumn::make(),
                LinkableColumn::make('url')
                    ->title(trans('core/base::tables.url'))
                    ->alignStart()
                    ->externalLink(),
                Column::make('status_code')
                    ->title(trans('plugins/request-log::request-log.status_code')),
                Column::make('count')
                    ->title(trans('plugins/request-log::request-log.count')),
            ])
            ->addHeaderActions([
                HeaderAction::make('empty')
                    ->label(trans('plugins/request-log::request-log.delete_all'))
                    ->icon('ti ti-trash')
                    ->url('javascript:void(0)')
                    ->attributes(['class' => 'empty-request-logs-button']),
            ])
            ->addAction(DeleteAction::make()->route('request-log.destroy'))
            ->addBulkAction(DeleteBulkAction::make()->permission('request-log.destroy'))
            ->queryUsing(function (Builder $query): void {
                $query
                    ->select([
                        'id',
                        'url',
                        'status_code',
                        'count',
                    ]);
            });
    }
}
