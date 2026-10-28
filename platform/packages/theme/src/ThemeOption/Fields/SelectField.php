<?php

namespace Kern\Theme\ThemeOption\Fields;

use Kern\Theme\Concerns\ThemeOption\Fields\HasOptions;
use Kern\Theme\ThemeOption\ThemeOptionField;

class SelectField extends ThemeOptionField
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'customSelect';
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'attributes' => [
                ...parent::toArray()['attributes'],
                'choices' => $this->options,
            ],
        ];
    }
}
