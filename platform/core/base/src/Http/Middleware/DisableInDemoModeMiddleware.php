<?php

namespace Kern\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kern\Base\Exceptions\DisabledInDemoModeException;
use Kern\Base\Facades\BaseHelper;

class DisableInDemoModeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            throw new DisabledInDemoModeException;
        }

        return $next($request);
    }
}
