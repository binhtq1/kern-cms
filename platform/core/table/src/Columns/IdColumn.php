<?php

namespace Kern\Table\Columns;

use Kern\Base\Models\BaseModel;
use Kern\Table\Contracts\FormattedColumn as FormattedColumnContract;

class IdColumn extends FormattedColumn implements FormattedColumnContract
{
    public static function make(array|string $data = [], string $name = ''): static
    {
        return parent::make($data ?: 'id', $name)
            ->title(trans('core/base::tables.id'))
            ->alignCenter()
            ->width(20)
            ->columnVisibility();
    }

    public function formattedValue($value): ?string
    {
        return $this
            ->when(BaseModel::isUsingStringId(), function (IdColumn $column) {
                return $column->limit();
            })
            ->applyLimitIfAvailable($value);
    }
}
