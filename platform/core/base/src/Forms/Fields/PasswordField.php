<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FormField;

class PasswordField extends FormField
{
    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.password';
    }
}
