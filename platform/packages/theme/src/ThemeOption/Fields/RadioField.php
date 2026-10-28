<?php

namespace Kern\Theme\ThemeOption\Fields;

use Kern\Theme\Concerns\ThemeOption\Fields\HasOptions;
use Kern\Theme\ThemeOption\ThemeOptionField;

class RadioField extends ThemeOptionField
{
    use HasOptions;

    protected bool $inline = true;

    public function inline(bool $inline): static
    {
        $this->inline = $inline;

        return $this;
    }

    public function fieldType(): string
    {
        return 'customRadio';
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'attributes' => [
                ...parent::toArray()['attributes'],
                'choices' => $this->options,
                'value' => $this->getValue(),
                'attr' => [
                    'inline' => $this->inline,
                ],
            ],
        ];
    }
}
