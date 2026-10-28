<?php

namespace Kern\Theme\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Routing\Router;
use Kern\Base\Events\Event;

class ThemeRoutingBeforeEvent extends Event
{
    use SerializesModels;

    public function __construct(public Router $router) {}
}
