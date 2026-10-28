<?php

namespace Kern\Base\Forms\FieldTypes;

use Kern\Base\Traits\Forms\CanSpanColumns;

class StaticType extends \Kris\LaravelFormBuilder\Fields\StaticType
{
    use CanSpanColumns;
}
