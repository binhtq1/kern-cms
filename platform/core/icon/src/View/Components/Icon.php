<?php

namespace Kern\Icon\View\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Kern\Icon\Facades\Icon as IconFacade;

class Icon extends Component
{
    public function __construct(
        public string $name,
        public ?string $size = null
    ) {}

    public function render(): Closure
    {
        return function (array $data) {
            $attributes = $data['attributes']->getIterator()->getArrayCopy();
            $class = trim(sprintf('%s %s', $this->size ? "icon-{$this->size}" : '', $attributes['class'] ?? ''));

            unset($attributes['class']);

            if (str_starts_with($this->name, 'ti ti-')) {
                $class = rtrim($class).' svg-icon-'.str_replace(' ', '-', $this->name);

                return IconFacade::render(
                    Str::after($this->name, '-'),
                    ['class' => $class, ...$attributes]
                );
            }

            return sprintf('<i %s></i>', $data['attributes']->class(trim("$this->name $class")));
        };
    }
}
