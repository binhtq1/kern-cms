<?php

namespace Kern\Base\Forms\FieldTypes;

use Kern\Base\Traits\Forms\CanSpanColumns;

class RepeatedType extends \Kris\LaravelFormBuilder\Fields\RepeatedType
{
    use CanSpanColumns;
}
