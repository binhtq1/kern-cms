<?php

namespace Kern\Slug\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kern\Base\Events\Event;
use Kern\Slug\Models\Slug;

class UpdatedSlugEvent extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public bool|Model|null $data, public Slug $slug) {}
}
