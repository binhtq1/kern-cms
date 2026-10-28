<?php

namespace Kern\Menu\Widgets\Fronts;

use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Menu\Models\Menu;
use Kern\Widget\AbstractWidget;
use Kern\Widget\Forms\WidgetForm;

class CustomMenu extends AbstractWidget
{
    public function __construct()
    {
        parent::__construct([
            'name' => __('Custom Menu'),
            'description' => __('Add a custom menu to your widget area.'),
            'menu_id' => null,
        ]);
    }

    protected function settingForm(): WidgetForm|string|null
    {
        return WidgetForm::createFromArray($this->getConfig())
            ->add('name', TextField::class, NameFieldOption::make())
            ->add(
                'menu_id',
                SelectField::class,
                SelectFieldOption::make()
                    ->label(__('Menu'))
                    ->choices(Menu::query()->pluck('name', 'slug')->all())
                    ->searchable()
            );
    }
}
