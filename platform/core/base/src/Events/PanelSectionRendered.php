<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Contracts\PanelSections\PanelSection;

class PanelSectionRendered
{
    use Dispatchable;

    public function __construct(public PanelSection $section, public string $content) {}
}
