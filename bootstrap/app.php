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
        // Resolve the tenant from the subdomain on every web AND api request.
        // Passes through silently when on the main domain (no subdomain).
        // API routes need it so /api/payment/initiate and /api/payment/status
        // have tenant() available (called from subdomain portal via fetch()).
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
