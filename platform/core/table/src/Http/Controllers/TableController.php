<?php

namespace Kern\Table\Http\Controllers;

use Kern\Base\Http\Controllers\BaseController;
use Kern\Table\TableBuilder;

class TableController extends BaseController
{
    public function __construct(protected TableBuilder $tableBuilder) {}
}
