<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FieldTypes\CheckableType;

class CheckboxField extends CheckableType
{
    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.checkbox';
    }
}
