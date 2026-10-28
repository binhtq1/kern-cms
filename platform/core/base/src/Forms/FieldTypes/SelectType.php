<?php

namespace Kern\Base\Forms\FieldTypes;

use Kern\Base\Traits\Forms\CanSpanColumns;

class SelectType extends \Kris\LaravelFormBuilder\Fields\SelectType
{
    use CanSpanColumns;
}
