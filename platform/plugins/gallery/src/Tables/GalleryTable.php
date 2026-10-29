<?php

namespace Kern\Gallery\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\Gallery\Models\Gallery;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\Actions\EditAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\BulkChanges\CreatedAtBulkChange;
use Kern\Table\BulkChanges\NameBulkChange;
use Kern\Table\BulkChanges\StatusBulkChange;
use Kern\Table\Columns\Column;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\ImageColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\Columns\StatusColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class GalleryTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(Gallery::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('galleries.create'))
            ->addColumns([
                IdColumn::make(),
                ImageColumn::make(),
                NameColumn::make()->route('galleries.edit'),
                Column::make('order')
                    ->title(trans('core/base::tables.order'))
                    ->width(100),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addActions([
                EditAction::make()->route('galleries.edit'),
                DeleteAction::make()->route('galleries.destroy'),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('galleries.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make(),
                StatusBulkChange::make(),
                CreatedAtBulkChange::make(),
            ])
            ->queryUsing(function (Builder $query) {
                return $query
                    ->select([
                        'id',
                        'name',
                        'order',
                        'created_at',
                        'status',
                        'image',
                    ]);
            });
    }
}
