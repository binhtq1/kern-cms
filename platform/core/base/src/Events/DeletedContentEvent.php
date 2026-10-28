<?php

namespace Kern\Base\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class DeletedContentEvent extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public string $screen, public Request $request, public bool|Model|null $data) {}
}
