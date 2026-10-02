<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA: cookie-based session auth for requests coming from the frontend domain.
        $middleware->statefulApi();

        // Global per-user/IP limit for the whole API (limiter defined in AppServiceProvider).
        $middleware->throttleApi();

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'super-admin' => EnsureSuperAdmin::class,
            'active'      => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
