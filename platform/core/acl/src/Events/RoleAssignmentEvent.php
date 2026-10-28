<?php

namespace Kern\ACL\Events;

use Illuminate\Queue\SerializesModels;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\Base\Events\Event;

class RoleAssignmentEvent extends Event
{
    use SerializesModels;

    public function __construct(public Role $role, public User $user) {}
}
