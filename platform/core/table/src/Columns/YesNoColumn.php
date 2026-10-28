<?php

namespace Kern\Table\Columns;

use Kern\Table\Contracts\FormattedColumn as FormattedColumnContract;

class YesNoColumn extends FormattedColumn implements FormattedColumnContract
{
    public static function make(array|string $data = [], string $name = ''): static
    {
        return parent::make($data, $name)
            ->width(100);
    }

    public function formattedValue($value): string
    {
        return view('core/table::includes.columns.yes-no', compact('value'))->render();
    }
}
