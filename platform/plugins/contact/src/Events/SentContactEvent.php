<?php

namespace Kern\Contact\Events;

use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;
use Kern\Base\Models\BaseModel;

class SentContactEvent extends Event
{
    use SerializesModels;

    public function __construct(public bool|BaseModel|null $data) {}
}
