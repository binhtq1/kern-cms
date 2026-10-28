<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FieldTypes\SelectType;

class UiSelectorField extends SelectType
{
    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.ui-selector';
    }
}
