<?php

namespace Kern\CustomField\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Tables\Actions\ExportAction;
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
use Kern\Table\HeaderActions\HeaderAction;

class CustomFieldTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(FieldGroup::class)
            ->addHeaderActions([
                CreateHeaderAction::make()->route('custom-fields.create'),
                HeaderAction::make('import-field-group')
                    ->label(trans('plugins/custom-field::base.import'))
                    ->icon('ti ti-cloud-upload')
                    ->url('#')
                    ->attributes(['class' => 'custom-import-button']),
            ])
            ->addColumns([
                IdColumn::make(),
                NameColumn::make('title')->route('custom-fields.edit'),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addActions([
                ExportAction::make()
                    ->route('custom-fields.export')
                    ->permission('custom-fields.index'),
                EditAction::make()->route('custom-fields.edit'),
                DeleteAction::make()->route('custom-fields.destroy'),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('custom-fields.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make()->name('title'),
                StatusBulkChange::make(),
                CreatedAtBulkChange::make(),
            ])
            ->queryUsing(function (Builder $query) {
                return $query
                    ->select([
                        'id',
                        'title',
                        'status',
                        'order',
                        'created_at',
                    ]);
            });
    }
}
