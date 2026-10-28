<?php

namespace Kern\Base\Forms\Fields;

use Kern\Base\Forms\FieldTypes\SelectType;

class RadioField extends SelectType
{
    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.custom-radio';
    }

    public function getDefaults(): array
    {
        return [
            ...parent::getDefaults(),
            'attr' => ['class' => null, 'id' => $this->getName()],
        ];
    }
}
