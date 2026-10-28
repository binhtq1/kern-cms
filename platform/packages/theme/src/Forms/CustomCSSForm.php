<?php

namespace Kern\Theme\Forms;

use Illuminate\Support\Facades\File;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Forms\FieldOptions\CodeEditorFieldOption;
use Kern\Base\Forms\Fields\CodeEditorField;
use Kern\Base\Forms\FormAbstract;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Http\Requests\CustomCssRequest;

class CustomCSSForm extends FormAbstract
{
    public function setup(): void
    {
        $css = null;
        $file = Theme::getStyleIntegrationPath();

        if (File::exists($file)) {
            $css = BaseHelper::getFileData($file, false);
        }

        $this
            ->setUrl(route('theme.custom-css.post'))
            ->setValidatorClass(CustomCssRequest::class)
            ->setActionButtons(view('core/base::forms.partials.form-actions', ['onlySave' => true])->render())
            ->add(
                'custom_css',
                CodeEditorField::class,
                CodeEditorFieldOption::make()
                    ->label(trans('packages/theme::theme.custom_css'))
                    ->value($css)
                    ->mode('css')
                    ->maxLength(100000)
            );
    }
}
