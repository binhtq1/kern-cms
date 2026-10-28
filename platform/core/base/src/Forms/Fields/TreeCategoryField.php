<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FormField;

class TreeCategoryField extends FormField
{
    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.tree-categories';
    }
}
