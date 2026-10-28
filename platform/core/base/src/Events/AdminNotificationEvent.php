<?php

namespace Kern\Base\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Supports\AdminNotificationItem;

class AdminNotificationEvent extends Event
{
    use SerializesModels;

    public function __construct(public AdminNotificationItem $item) {}
}
