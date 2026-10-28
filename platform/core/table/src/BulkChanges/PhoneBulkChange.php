<?php

namespace Kern\Table\BulkChanges;

use Kern\Base\Facades\BaseHelper;
use Kern\Table\Abstracts\TableBulkChangeAbstract;

class PhoneBulkChange extends TableBulkChangeAbstract
{
    public static function make(array $data = []): static
    {
        return parent::make()
            ->name('phone')
            ->title(trans('core/base::tables.phone'))
            ->type('text')
            ->validate('required|'.BaseHelper::getPhoneValidationRule());
    }
}
