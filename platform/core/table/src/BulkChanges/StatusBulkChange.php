<?php

namespace Kern\Table\BulkChanges;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;

class StatusBulkChange extends SelectBulkChange
{
    public static function make(array $data = []): static
    {
        return parent::make()
            ->name('status')
            ->title(trans('core/base::tables.status'))
            ->type('customSelect')
            ->choices(BaseStatusEnum::labels());
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'validate' => ['required', Rule::in(array_keys($this->choices))],
        ];
    }
}
