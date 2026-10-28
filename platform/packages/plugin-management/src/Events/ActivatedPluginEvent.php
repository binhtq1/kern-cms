<?php

namespace Kern\PluginManagement\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;

class ActivatedPluginEvent extends Event
{
    use SerializesModels;

    public function __construct(public string $plugin) {}
}
