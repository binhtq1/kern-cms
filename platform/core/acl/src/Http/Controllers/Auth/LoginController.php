<?php

namespace Kern\ACL\Http\Controllers\Auth;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Kern\ACL\Http\Requests\LoginRequest;
use Kern\ACL\Models\User;
use Kern\ACL\Supports\AdminAuthData;
use Kern\ACL\Traits\AuthenticatesUsers;
use Kern\Base\Http\Controllers\BaseController;

class LoginController extends BaseController
{
    use AuthenticatesUsers;

    protected string $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);

        $this->redirectTo = route('dashboard.index');
    }

    public function showLoginForm()
    {
        $this->pageTitle(trans('core/acl::auth.login_title'));

        return app(AdminAuthData::class)->render();
    }

    public function login(LoginRequest $request)
    {
        $request->merge([$this->username() => $request->input('username')]);

        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            $this->sendLockoutResponse($request);
        }

        $user = User::query()->where([$this->username() => $request->input($this->username())])->first();
        if (! empty($user)) {
            if (! $user->activated) {
                if ($request->header('X-Inertia')) {
                    throw ValidationException::withMessages(['username' => trans('core/acl::auth.login.not_active')]);
                }

                return $this->httpResponse()
                    ->setError()
                    ->setMessage(trans('core/acl::auth.login.not_active'));
            }
        }

        return app(Pipeline::class)->send($request)
            ->through(apply_filters('core_acl_login_pipeline', [
                function (Request $request, Closure $next) {
                    if ($this->guard()->attempt(
                        $this->credentials($request),
                        $request->boolean('remember')
                    )) {
                        return $next($request);
                    }

                    $this->incrementLoginAttempts($request);

                    return $this->sendFailedLoginResponse();
                },
            ]))
            ->then(function (Request $request) {
                $user = Auth::guard()->user();
                $user->last_login = Carbon::now();
                $user->save();

                return $this->sendLoginResponse($request);
            });
    }

    public function username()
    {
        return filter_var(request()->input('username'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    public function logout(Request $request)
    {
        do_action(AUTH_ACTION_AFTER_LOGOUT_SYSTEM, $request, $request->user());

        $this->guard()->logout();

        $request->session()->invalidate();

        return $this->httpResponse()
            ->setNextRoute('access.login')
            ->setMessage(trans('core/acl::auth.login.logout_success'));
    }
}
