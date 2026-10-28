<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Widgets\Contracts\AdminWidget;

class RenderingAdminWidgetEvent
{
    use Dispatchable;

    public function __construct(public AdminWidget $widget) {}
}
