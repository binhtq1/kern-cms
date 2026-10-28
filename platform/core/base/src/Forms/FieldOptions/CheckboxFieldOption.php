<?php

namespace Kern\Base\Forms\FieldOptions;

use Kern\Base\Forms\FormFieldOptions;

class CheckboxFieldOption extends FormFieldOptions
{
    protected array|bool|string|int|null $value;

    protected bool $checked;

    public function value(array|bool|string|int|null $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): array|bool|string|int|null
    {
        return $this->value;
    }

    public function checked(bool $checked): static
    {
        $this->checked = $checked;

        return $this;
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        if (isset($this->value)) {
            $data['value'] = $this->getValue();
        }

        if (isset($this->checked)) {
            $data['checked'] = $this->checked;
        }

        return $data;
    }
}
