<?php

namespace Kern\ACL\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Kern\ACL\Events\RoleAssignmentEvent;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\Support\Services\ProduceServiceInterface;

class CreateUserService implements ProduceServiceInterface
{
    public function __construct(protected ActivateUserService $activateUserService) {}

    public function execute(Request $request): User
    {
        $user = new User;
        $user->fill($request->only(['first_name', 'last_name', 'username', 'email']));
        $user->password = Hash::make($request->input('password'));
        $user->save();

        if (
            $this->activateUserService->activate($user) &&
            ($roleId = $request->input('role_id')) &&
            $role = Role::query()->find($roleId)
        ) {
            /**
             * @var Role $role
             */
            $role->users()->attach($user->getKey());

            event(new RoleAssignmentEvent($role, $user));
        }

        return $user;
    }
}
