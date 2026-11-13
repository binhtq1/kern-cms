<?php

namespace Kern\Blog\Widgets\Fronts;

use Illuminate\Support\Collection;
use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\NumberFieldOption;
use Kern\Base\Forms\Fields\NumberField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Widget\AbstractWidget;
use Kern\Widget\Forms\WidgetForm;

class Tags extends AbstractWidget
{
    public function __construct()
    {
        parent::__construct([
            'name' => __('Tags'),
            'description' => __('Popular tags'),
            'number_display' => 5,
        ]);
    }

    protected function data(): array|Collection
    {
        return [
            'tags' => get_popular_tags((int) $this->getConfig('number_display')),
        ];
    }

    protected function settingForm(): WidgetForm|string|null
    {
        return WidgetForm::createFromArray($this->getConfig())
            ->add('name', TextField::class, NameFieldOption::make())
            ->add(
                'number_display',
                NumberField::class,
                NumberFieldOption::make()
                    ->label(__('Number tags to display'))
            );
    }

    protected function requiredPlugins(): array
    {
        return ['blog'];
    }
}
