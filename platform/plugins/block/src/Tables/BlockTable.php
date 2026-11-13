<?php

namespace Kern\Block\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\Base\Facades\Html;
use Kern\Block\Models\Block;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\Actions\EditAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\BulkChanges\CreatedAtBulkChange;
use Kern\Table\BulkChanges\NameBulkChange;
use Kern\Table\BulkChanges\StatusBulkChange;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\FormattedColumn;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\Columns\StatusColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class BlockTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(Block::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('block.create'))
            ->addColumns([
                IdColumn::make(),
                NameColumn::make()->route('block.edit'),
                FormattedColumn::make('alias')
                    ->title(trans('core/base::tables.shortcode'))
                    ->alignStart()
                    ->getValueUsing(function (FormattedColumn $column) {
                        $value = $column->getItem()->alias;

                        if (! function_exists('shortcode')) {
                            return $value;
                        }

                        return shortcode()->generateShortcode('static-block', ['alias' => $value]);
                    })
                    ->renderUsing(fn (FormattedColumn $column) => Html::tag('code', $column->getValue()))
                    ->copyable()
                    ->copyableState(function (FormattedColumn $column) {
                        $value = $column->getItem()->alias;

                        if (! function_exists('shortcode')) {
                            return $value;
                        }

                        return shortcode()->generateShortcode('static-block', ['alias' => $value]);
                    }),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addActions([
                EditAction::make()->route('block.edit'),
                DeleteAction::make()->route('block.destroy'),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('block.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make(),
                StatusBulkChange::make(),
                CreatedAtBulkChange::make(),
            ])
            ->queryUsing(function (Builder $query): void {
                $query
                    ->select([
                        'id',
                        'alias',
                        'name',
                        'created_at',
                        'status',
                    ]);
            });
    }
}
