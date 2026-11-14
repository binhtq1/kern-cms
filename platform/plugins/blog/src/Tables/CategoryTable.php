<?php

namespace Kern\Blog\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\Blog\Models\Category;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\Actions\EditAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\BulkChanges\CreatedAtBulkChange;
use Kern\Table\BulkChanges\NameBulkChange;
use Kern\Table\BulkChanges\StatusBulkChange;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\Columns\StatusColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class CategoryTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(Category::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('categories.create'))
            ->addColumns([
                IdColumn::make(),
                NameColumn::make()->route('categories.edit'),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addActions([
                EditAction::make()->route('categories.edit'),
                DeleteAction::make()->route('categories.destroy'),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('categories.destroy'),
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
                        'created_at',
                        'status',
                    ]);
            });
    }
}
