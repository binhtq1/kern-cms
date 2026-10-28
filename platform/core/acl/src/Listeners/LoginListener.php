<?php

namespace Kern\ACL\Listeners;

use Illuminate\Auth\Events\Login;
use Kern\ACL\Models\User;

class LoginListener
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }
    }
}
