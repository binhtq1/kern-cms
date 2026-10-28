<?php

namespace Kern\RequestLog\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;

class RequestHandlerEvent extends Event
{
    use SerializesModels;

    public function __construct(public int $code) {}
}
