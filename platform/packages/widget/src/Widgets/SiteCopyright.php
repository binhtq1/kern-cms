<?php

namespace Kern\Widget\Widgets;

use Illuminate\Support\Collection;
use Kern\Base\Facades\Html;
use Kern\Base\Forms\FieldOptions\HtmlFieldOption;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Theme\Supports\ThemeSupport;
use Kern\Widget\AbstractWidget;
use Kern\Widget\Forms\WidgetForm;

class SiteCopyright extends AbstractWidget
{
    public function __construct()
    {
        parent::__construct([
            'name' => __('Site Copyright'),
            'description' => __('Copyright text at the bottom footer.'),
        ]);
    }

    protected function settingForm(): WidgetForm|string|null
    {
        return WidgetForm::createFromArray($this->getConfig())
            ->add(
                'description',
                HtmlField::class,
                HtmlFieldOption::make()
                    ->content(
                        __('Go to :link to change the copyright text.', [
                            'link' => Html::link(route('theme.options'), __('Theme options')),
                        ])
                    )
            );
    }

    protected function data(): array|Collection
    {
        return [
            'copyright' => ThemeSupport::getSiteCopyright(),
        ];
    }
}
