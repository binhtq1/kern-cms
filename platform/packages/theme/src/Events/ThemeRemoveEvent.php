<?php

namespace Kern\Theme\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;

class ThemeRemoveEvent extends Event
{
    use SerializesModels;

    public function __construct(public string $theme) {}
}
