<?php

namespace Kern\Theme\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;
use Kern\Slug\Models\Slug;

class RenderingSingleEvent extends Event
{
    use SerializesModels;

    public function __construct(public Slug $slug) {}
}
