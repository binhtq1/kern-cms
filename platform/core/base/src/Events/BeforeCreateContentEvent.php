<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Kern\Base\Contracts\BaseModel;

class BeforeCreateContentEvent extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Request $request, public bool|BaseModel|null $data) {}
}
