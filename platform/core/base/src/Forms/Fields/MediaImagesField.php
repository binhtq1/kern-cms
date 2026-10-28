<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Facades\Assets;
use Kern\Base\Forms\FormField;

class MediaImagesField extends FormField
{
    protected function getTemplate(): string
    {
        Assets::addScripts(['jquery-ui']);

        return 'core/base::forms.fields.media-images';
    }
}
