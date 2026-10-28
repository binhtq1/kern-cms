<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Facades\Assets;
use Kern\Base\Forms\FormField;

class TimePickerField extends FormField
{
    protected function getTemplate(): string
    {
        Assets::addScripts(['timepicker'])
            ->addStyles(['timepicker']);

        return 'core/base::forms.fields.time-picker';
    }
}
