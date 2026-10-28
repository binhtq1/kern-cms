<?php

namespace Kern\Api\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\Api\Models\PersonalAccessToken;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\Columns\Column;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\DateTimeColumn;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class SanctumTokenTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(PersonalAccessToken::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('api.sanctum-token.create'))
            ->addAction(DeleteAction::make()->route('api.sanctum-token.destroy'))
            ->addColumns([
                IdColumn::make(),
                NameColumn::make(),
                Column::make('abilities')
                    ->label(trans('packages/api::sanctum-token.abilities')),
                DateTimeColumn::make('last_used_at')
                    ->label(trans('packages/api::sanctum-token.last_used_at')),
                CreatedAtColumn::make(),
            ])
            ->addBulkAction(DeleteBulkAction::make())
            ->queryUsing(fn (Builder $query) => $query->select([
                'id',
                'name',
                'abilities',
                'last_used_at',
                'created_at',
            ]));
    }
}
