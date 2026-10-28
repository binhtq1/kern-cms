<?php

namespace Kern\ACL\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Kern\ACL\Models\User;
use Kern\Base\Facades\BaseHelper;
use Kern\Support\Services\ProduceServiceInterface;
use Throwable;

class ChangePasswordService implements ProduceServiceInterface
{
    public function execute(Request $request): bool|User
    {
        $currentUser = $request->user();

        if (! $currentUser->isSuperUser()) {
            if (! Hash::check($request->input('old_password'), $currentUser->getAuthPassword())) {
                throw new Exception(trans('core/acl::users.current_password_not_valid'));
            }
        }

        if (($userId = $request->input('id')) && $userId === $currentUser->getKey()) {
            $user = $currentUser;
        } else {
            $user = User::query()->findOrFail($userId);
        }

        $password = $request->input('password');

        $user->password = Hash::make($password);
        $user->save();

        /**
         * @var User $user
         */
        if ($user->getKey() != $currentUser->getKey()) {
            try {
                Auth::setUser($user);
                Auth::logoutOtherDevices($password);
            } catch (Throwable $exception) {
                BaseHelper::logError($exception);
            } finally {
                Auth::setUser($currentUser);
            }
        }

        do_action(USER_ACTION_AFTER_UPDATE_PASSWORD, USER_MODULE_SCREEN_NAME, $request, $user);

        return $user;
    }
}
