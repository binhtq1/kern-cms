<?php

namespace Kern\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureReactAdminRoutes extends HandleAdminInertiaRequests
{
    public function handle(Request $request, Closure $next, string $module = 'system'): Response
    {
        if ($request->route()?->getName() === 'access.logout') {
            return $next($request);
        }

        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if ($middleware === HandleAdminInertiaRequests::class || str_starts_with($middleware, HandleAdminInertiaRequests::class.':')) {
                return $next($request);
            }
        }

        $contracts = [
            'table.bulk-change.data', 'table.bulk-change.save', 'table.bulk-action.dispatch',
            'table.filter.input', 'table.update-columns-visibility',
            'short-codes.ajax-get-admin-config',
        ];
        if (in_array($request->route()?->getName(), $contracts, true)) {
            return parent::handle($request, $next, $module);
        }

        return parent::handle($request, function (Request $request): Response {
            $message = 'This admin route must register the React admin middleware and provide an Inertia page or a JSON/download contract.';
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => true, 'message' => $message], 503);
            }

            return Inertia::render('System/Compatibility', [
                'title' => 'Admin route requires an update',
                'message' => $message,
            ])->toResponse($request)->setStatusCode(503);
        }, $module);
    }
}
