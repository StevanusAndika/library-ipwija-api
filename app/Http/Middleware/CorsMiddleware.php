<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow semua origin untuk testing
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', '*');
        $response->headers->set('Access-Control-Allow-Methods', '*');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
