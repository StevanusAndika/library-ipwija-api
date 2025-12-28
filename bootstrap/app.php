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
            'mahasiswa' => \App\Http\Middleware\MahasiswaMiddleware::class,
            'user' => \App\Http\Middleware\UserMiddleware::class,
            // 'cors' => \App\Http\Middleware\CorsMiddleware::class,
            // 'jwt' => \Tymon\JWTAuth\Middleware\GetUserFromToken::class,
            // 'jwt.refresh' => \Tymon\JWTAuth\Middleware\RefreshToken::class,
        ]);

        // Add CORS middleware to global middleware
        // $middleware->appendToGroup('api', \App\Http\Middleware\CorsMiddleware::class);
        // $middleware->appendToGroup('web', \App\Http\Middleware\CorsMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
