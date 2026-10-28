<?php

namespace Kern\Theme\ThemeOption\Fields;

use Kern\Theme\ThemeOption\ThemeOptionField;

class MediaImageField extends ThemeOptionField
{
    public function fieldType(): string
    {
        return 'mediaImage';
    }
}
