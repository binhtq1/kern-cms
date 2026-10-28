<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Contracts\PanelSections\Manager;

class PanelSectionsRendered
{
    use Dispatchable;

    public function __construct(public Manager $panelSectionManager) {}
}
