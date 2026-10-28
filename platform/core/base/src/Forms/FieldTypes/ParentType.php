<?php

namespace Kern\Base\Forms\FieldTypes;

use Kern\Base\Traits\Forms\CanSpanColumns;

abstract class ParentType extends \Kris\LaravelFormBuilder\Fields\ParentType
{
    use CanSpanColumns;
}
