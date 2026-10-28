<?php

namespace Kern\Base\Events;

use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Kern\Base\Contracts\BaseModel;

class BeforeEditContentEvent extends Event
{
    use SerializesModels;

    public function __construct(public Request $request, public bool|BaseModel|null $data) {}
}
