<?php

namespace Kern\Base\Forms\FieldOptions;

use Closure;
use Kern\Base\Forms\FormFieldOptions;

class HtmlFieldOption extends FormFieldOptions
{
    protected string $html = '';

    public function view(string $view, array $data = [], array $mergeData = []): static
    {
        return $this->content(
            view($view, $data, $mergeData)->render()
        );
    }

    public function content(string|Closure $content): static
    {
        $this->html = value($content);

        return $this;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'html' => $this->html,
        ];
    }
}
