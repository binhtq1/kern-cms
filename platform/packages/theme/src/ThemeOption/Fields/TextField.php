<?php

namespace Kern\Theme\ThemeOption\Fields;

use Kern\Theme\ThemeOption\ThemeOptionField;

class TextField extends ThemeOptionField
{
    public function fieldType(): string
    {
        return 'text';
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'attributes' => [
                ...parent::toArray()['attributes'],
                'value' => $this->getValue(),
                'options' => [
                    'class' => 'form-control',
                ],
            ],
        ];
    }
}
