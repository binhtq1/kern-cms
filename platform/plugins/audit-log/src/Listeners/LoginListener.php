<?php

namespace Kern\AuditLog\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Kern\ACL\Models\User;
use Kern\AuditLog\Models\AuditHistory;

class LoginListener
{
    public function __construct(protected Request $request) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        AuditHistory::query()->create([
            'user_agent' => $this->request->userAgent(),
            'ip_address' => $this->request->ip(),
            'module' => 'to the system',
            'action' => 'logged in',
            'user_id' => $user->getKey(),
            'reference_user' => 0,
            'reference_id' => $user->getKey(),
            'reference_name' => $user->name,
            'type' => 'info',
        ]);
    }
}
