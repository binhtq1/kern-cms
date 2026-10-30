<?php

namespace Kern\Contact\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\Contact\Models\CustomField;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\Actions\EditAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\BulkChanges\CreatedAtBulkChange;
use Kern\Table\BulkChanges\NameBulkChange;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\EnumColumn;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class CustomFieldTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(CustomField::class)
            ->addHeaderActions([
                CreateHeaderAction::make()->route('contacts.custom-fields.create')->permission('contacts.edit'),
            ])
            ->addBulkChanges([
                NameBulkChange::make()->validate('required|max:120'),
                CreatedAtBulkChange::make(),
            ])
            ->addBulkAction(DeleteBulkAction::make()->permission('contacts.edit'))
            ->addActions([
                EditAction::make()->route('contacts.custom-fields.edit')->permission('contacts.edit'),
                DeleteAction::make()->route('contacts.custom-fields.destroy')->permission('contacts.edit'),
            ])
            ->addColumns([
                IdColumn::make(),
                NameColumn::make()->route('contacts.custom-fields.edit')->permission('contacts.edit'),
                EnumColumn::make('type')
                    ->title(trans('plugins/contact::contact.custom_field.type'))
                    ->alignLeft(),
                CreatedAtColumn::make(),
            ])
            ->queryUsing(fn (Builder $query) => $query->select([
                'id',
                'name',
                'type',
                'created_at',
            ]));
    }
}
