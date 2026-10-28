<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FormField;

class MediaFileField extends FormField
{
    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.media-file';
    }
}
