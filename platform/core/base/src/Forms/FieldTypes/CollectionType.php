<?php

namespace Kern\Base\Forms\FieldTypes;

use Kern\Base\Traits\Forms\CanSpanColumns;

class CollectionType extends \Kris\LaravelFormBuilder\Fields\CollectionType
{
    use CanSpanColumns;
}
