<?php

namespace Kern\ACL\Forms\Auth;

use Kern\Base\Forms\FieldOptions\HtmlFieldOption;
use Kern\Base\Forms\FieldOptions\TextFieldOption;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Models\BaseModel;

class AuthForm extends FormAbstract
{
    public function setup(): void
    {
        $this->model(BaseModel::class);
    }

    public function heading(string $heading): self
    {
        $this->add(
            'heading',
            HtmlField::class,
            HtmlFieldOption::make()
                ->content(sprintf(
                    '<h2 class="h3 text-center mb-3">%s</h2>',
                    $heading
                ))
        );

        return $this;
    }

    public function submitButton(string $label, ?string $icon = null): self
    {
        $this->add('submit', 'submit', TextFieldOption::make()->label($label));

        return $this;
    }
}
