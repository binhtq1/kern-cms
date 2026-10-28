<?php

namespace Kern\ACL\Events;

use Illuminate\Queue\SerializesModels;
use Kern\ACL\Models\Role;
use Kern\Base\Events\Event;

class RoleUpdateEvent extends Event
{
    use SerializesModels;

    public function __construct(public Role $role) {}
}
