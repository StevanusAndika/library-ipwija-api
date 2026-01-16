<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware alias
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'user' => \App\Http\Middleware\UserMiddleware::class,
            'mahasiswa' => \App\Http\Middleware\UserMiddleware::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
            // 'jwt' => \Tymon\JWTAuth\Middleware\GetUserFromToken::class,
            // 'jwt.refresh' => \Tymon\JWTAuth\Middleware\RefreshToken::class,
        ]);

        // Add CORS middleware to global middleware
         $middleware->appendToGroup('api', \App\Http\Middleware\CorsMiddleware::class);
        // $middleware->appendToGroup('web', \App\Http\Middleware\CorsMiddleware::class);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return route('login');
        });
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

        // Handle unauthenticated exception to return JSON
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login again.',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }
        });
    })->create();
