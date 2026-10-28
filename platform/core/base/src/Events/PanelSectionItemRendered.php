<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Contracts\PanelSections\PanelSectionItem;

class PanelSectionItemRendered
{
    use Dispatchable;

    public function __construct(public PanelSectionItem $item, public string $content) {}
}
