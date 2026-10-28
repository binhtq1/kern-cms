<?php

namespace Kern\Theme\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;

class RenderingHomePageEvent extends Event
{
    use SerializesModels;
}
