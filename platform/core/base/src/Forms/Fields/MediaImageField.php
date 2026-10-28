<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FieldTypes\FormField;

class MediaImageField extends FormField
{
    protected bool $useDefaultFieldClass = false;

    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.media-image';
    }
}
