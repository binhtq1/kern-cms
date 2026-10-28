<?php

namespace Kern\Base\Forms\FieldOptions;

use Kern\Base\Forms\FormFieldOptions;

class ButtonFieldOption extends FormFieldOptions
{
    public function cssClass(string $class): static
    {
        $cssClass = trim($this->getAttribute('class').' '.$class);

        if ($cssClass) {
            $this->addAttribute('class', $cssClass);
        } else {
            $this->removeAttribute('class');
        }

        return $this;
    }
}
