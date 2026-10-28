<?php

namespace Kern\Theme\Events;

use Kern\Base\Events\Event;

class RenderingSiteMapEvent extends Event
{
    public function __construct(public ?string $key = null) {}
}
