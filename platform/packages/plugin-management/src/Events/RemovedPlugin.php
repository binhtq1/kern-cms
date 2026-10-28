<?php

namespace Kern\PluginManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;

class RemovedPlugin extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public string $plugin) {}
}
