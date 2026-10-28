<?php

namespace Kern\Shortcode\Forms\Fields;

use Kern\Base\Facades\Assets;
use Kern\Base\Facades\Html;
use Kern\Base\Forms\FormField;

class ShortcodeTagsField extends FormField
{
    protected function getTemplate(): string
    {
        return 'packages/shortcode::forms.fields.tags';
    }

    public function render(array $options = [], $showLabel = true, $showField = true, $showError = true): string
    {
        return Assets::styleToHtml('tagify').
            Assets::scriptToHtml('tagify').
            Html::script('vendor/core/core/base/js/tags.js').
            parent::render($options, $showLabel, $showField, $showError);
    }
}
