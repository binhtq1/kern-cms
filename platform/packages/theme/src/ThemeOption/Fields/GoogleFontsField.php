<?php

namespace Kern\Theme\ThemeOption\Fields;

use Kern\Theme\ThemeOption\ThemeOptionField;

class GoogleFontsField extends ThemeOptionField
{
    public function fieldType(): string
    {
        return 'googleFonts';
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'attributes' => [
                ...parent::toArray()['attributes'],
                'value' => $this->getValue(),
            ],
        ];
    }
}
