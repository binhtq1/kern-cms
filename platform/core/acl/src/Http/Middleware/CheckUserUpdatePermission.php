<?php

namespace Kern\ACL\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kern\ACL\Models\User;
use Kern\Base\Http\Responses\BaseHttpResponse;

class CheckUserUpdatePermission
{
    public function handle(Request $request, Closure $next)
    {
        $currentUser = $request->user();

        /**
         * @var User $user
         */
        $user = $request->route('user');

        $hasRightToUpdate = $currentUser->hasPermission('users.edit')
            || $currentUser->getKey() === $user->getKey()
            || $currentUser->isSuperUser();

        if ($user->isSuperUser() && ! $currentUser->isSuperUser()) {
            abort(403);
        }

        if (! $hasRightToUpdate) {
            if ($request->header('X-Inertia')) {
                abort(403);
            }

            return BaseHttpResponse::make()
                ->setNextUrl($user->url)
                ->setError()
                ->setMessage(trans('core/acl::permissions.access_denied_message'));
        }

        return $next($request);
    }
}
