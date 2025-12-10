<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware alias
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'user' => \App\Http\Middleware\UserMiddleware::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        // Add CORS middleware to global middleware
        $middleware->appendToGroup('api', \App\Http\Middleware\CorsMiddleware::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\CorsMiddleware::class);

        // TAMBAHKAN INI: Rate limiting untuk SEMUA API routes
        // Ini akan menambah rate limiting default untuk semua route di bawah 'api' middleware group
       
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception untuk rate limit
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                    'rate_limit_info' => 'Rate limit exceeded. Please wait before making more requests.'
                ], 429);
            }
        });
    })->create();
