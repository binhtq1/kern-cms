<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Contracts\PanelSections\PanelSection;

class PanelSectionItemsRendered
{
    use Dispatchable;

    public function __construct(public PanelSection $section, public array $items, public string $content) {}
}
