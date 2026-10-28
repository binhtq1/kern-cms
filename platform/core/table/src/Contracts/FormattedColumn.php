<?php

namespace Kern\Table\Contracts;

use Kern\Base\Contracts\BaseModel;
use Kern\Table\Abstracts\TableAbstract;
use stdClass;

interface FormattedColumn
{
    public function formattedValue($value): ?string;

    public function renderCell(BaseModel|stdClass|array $item, TableAbstract $table): string;
}
