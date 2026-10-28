<?php

namespace Kern\Theme\Forms\Fields;

use Kern\Base\Forms\FormField;

class ThemeIconField extends FormField
{
    protected function getTemplate(): string
    {

        return 'packages/theme::fields.icons-field';
    }
}
