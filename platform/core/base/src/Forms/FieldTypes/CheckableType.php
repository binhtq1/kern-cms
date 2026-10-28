<?php

namespace Kern\Base\Forms\FieldTypes;

use Kern\Base\Traits\Forms\CanSpanColumns;

class CheckableType extends \Kris\LaravelFormBuilder\Fields\CheckableType
{
    use CanSpanColumns;
}
