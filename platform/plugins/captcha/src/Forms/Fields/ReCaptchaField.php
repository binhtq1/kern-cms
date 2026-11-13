<?php

namespace Kern\Captcha\Forms\Fields;

use Kern\Base\Forms\FormField;

class ReCaptchaField extends FormField
{
    protected function getTemplate(): string
    {
        return 'plugins/captcha::forms.fields.recaptcha';
    }
}
