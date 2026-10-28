<?php

namespace Kern\Installer\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CheckIfInstallingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $content = File::get(storage_path(INSTALLING_SESSION_NAME));
            $content = json_decode($content, true) ?? $content;

            $startingDate = Carbon::parse($content);

            if (! $content || $startingDate->diffInMinutes(Carbon::now(), absolute: true) > 30) {
                return redirect()->to('/');
            }
        } catch (Exception) {
            return redirect()->to('/');
        }

        return $next($request);
    }
}
