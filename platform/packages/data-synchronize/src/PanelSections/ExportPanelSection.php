<?php

namespace Kern\DataSynchronize\PanelSections;

use Kern\Base\PanelSections\PanelSection;

class ExportPanelSection extends PanelSection
{
    public function setup(): void
    {
        $this
            ->setId('data-synchronize-export')
            ->setTitle(trans('packages/data-synchronize::data-synchronize.export.name'))
            ->withPriority(99999);
    }
}
