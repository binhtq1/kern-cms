<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Kern\Base\Exceptions\ExceptionConfigurator;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): string => ! is_in_admin(true) && Route::has('public.member.login')
                ? route('public.member.login')
                : route('access.login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ExceptionConfigurator::configure($exceptions);
    })->create();
