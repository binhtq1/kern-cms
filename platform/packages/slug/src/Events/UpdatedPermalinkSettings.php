<?php

namespace Kern\Slug\Events;

use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;

class UpdatedPermalinkSettings extends Event
{
    use SerializesModels;

    public function __construct(public string $reference, public string $prefix, public Request $request) {}
}
