<?php

namespace Kern\Table\Actions;

use Kern\Base\Supports\Builders\HasAttributes;
use Kern\Base\Supports\Builders\HasColor;
use Kern\Base\Supports\Builders\HasIcon;
use Kern\Base\Supports\Builders\HasUrl;
use Kern\Table\Abstracts\TableActionAbstract;
use Kern\Table\Actions\Concerns\HasAction;

class Action extends TableActionAbstract
{
    use HasAction;
    use HasAttributes;
    use HasColor;
    use HasIcon;
    use HasUrl;

    protected string $type = 'a';

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCssClass(): string
    {
        if ($this->getAttribute('class')) {
            return '';
        }

        $classes = [
            'btn',
            'btn-sm',
        ];

        if ($this->hasIcon() && $this->isIconOnly()) {
            $classes[] = 'btn-icon';
        }

        $classes[] = $this->getColor();

        return implode(' ', $classes);
    }

    public function getAttributes(): array
    {
        if (! $this->getColor() && $this->color) {
            $this->addAttribute(
                'style',
                sprintf('background-color: %s !important; color: %s;', $this->color, $this->colorText ?? '#fff')
            );
        }

        if ($cssClass = $this->getCssClass()) {
            $this->attributes['class'] = explode(' ', $cssClass);
        }

        return $this->attributes;
    }
}
