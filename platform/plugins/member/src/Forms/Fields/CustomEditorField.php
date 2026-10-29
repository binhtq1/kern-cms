<?php

namespace Kern\Member\Forms\Fields;

use Illuminate\Support\Arr;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Forms\Fields\TextareaField;
use Kern\Base\Supports\Editor;

class CustomEditorField extends TextareaField
{
    public function render(array $options = [], $showLabel = true, $showField = true, $showError = true): string
    {
        (new Editor)->registerAssets();

        Arr::set(
            $options,
            'attr.class',
            Arr::get($options, 'attr.class').' form-control editor-'.BaseHelper::getRichEditor()
        );

        return parent::render($options, $showLabel, $showField, $showError);
    }
}
