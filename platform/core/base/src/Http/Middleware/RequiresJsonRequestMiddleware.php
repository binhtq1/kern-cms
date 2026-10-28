<?php

namespace Kern\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Symfony\Component\HttpFoundation\Response;

class RequiresJsonRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson()) {
            return BaseHttpResponse::make()->setNextUrl(url('/'));
        }

        return $next($request);
    }
}
