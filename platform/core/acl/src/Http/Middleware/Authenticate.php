<?php

namespace Kern\ACL\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Kern\Base\Supports\AdminRenderer;
use Kern\Base\Supports\AdminSessionContext;

class Authenticate extends BaseAuthenticate
{
    protected array $guards;

    public function handle($request, Closure $next, ...$guards)
    {
        $this->guards = $guards;

        $this->authenticate($request, $guards);

        if (! $guards && ! $request->isMethodSafe() && $request->hasHeader(AdminSessionContext::HEADER)) {
            if (AdminSessionContext::actor($request) === null) {
                return response()->json(['message' => 'The original account context is invalid. Keep your draft and reopen the form after signing in.'], 403, ['X-Admin-Draft-Forbidden' => '1']);
            }
            if (! AdminSessionContext::matches($request)) {
                return AdminSessionContext::recoveryResponse();
            }
        }

        if (! $guards) {
            $route = $request->route();
            $flag = $route->getAction('permission');
            if ($flag === null) {
                $flag = $route->getName();
            }

            $flag = preg_replace('/\.store$/', '.create', $flag);
            $flag = preg_replace('/\.update$/', '.edit', $flag);

            if ($flag && ! $request->user()->hasAnyPermission((array) $flag)) {
                if ($request->header('X-Inertia') && app(AdminRenderer::class)->routeModule() !== null) {
                    abort(403);
                }

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Unauthenticated.'], 401);
                }

                return redirect()->route('dashboard.index');
            }
        }

        return $next($request);
    }

    protected function redirectTo($request)
    {
        if ($this->guards || $request->expectsJson()) {
            return parent::redirectTo($request);
        }

        return route('access.login');
    }
}
