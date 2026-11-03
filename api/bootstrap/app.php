<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // An API has no login page: a guest gets 401, never a redirect to a route that does not exist (which was a 500).
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'owner' => \App\Http\Middleware\EnsureBusinessOwner::class,
            'locale' => \App\Http\Middleware\UseRequestLocale::class,
        ]);
        // Behind the host's proxy: read the visitor's address and the scheme from it, or
        // every rate limit would count all visitors as one. Not X-Forwarded-Host: a
        // forged one would change the links the API builds.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
