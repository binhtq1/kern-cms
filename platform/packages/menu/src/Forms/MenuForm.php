<?php

namespace Kern\Menu\Forms;

use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;
use Kern\Menu\Http\Requests\MenuRequest;
use Kern\Menu\Models\Menu;

class MenuForm extends FormAbstract
{
    public function setup(): void
    {

        $this
            ->model(Menu::class)
            ->setFormOption('class', 'form-save-menu')
            ->setValidatorClass(MenuRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->required()->maxLength(120))
            ->add('status', SelectField::class, StatusFieldOption::make())
            ->addMetaBoxes([
                'structure' => [
                    'wrap' => false,
                    'content' => function () {
                        /**
                         * @var Menu $menu
                         */
                        $menu = $this->getModel();

                        return view('packages/menu::menu-structure', [
                            'menu' => $menu,
                            'locations' => $menu->getKey() ? $menu->locations()->pluck('location')->all() : [],
                        ])->render();
                    },
                ],
            ])
            ->setBreakFieldPoint('status');
    }
}
