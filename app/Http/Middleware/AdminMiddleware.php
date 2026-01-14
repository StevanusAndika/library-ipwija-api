<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah user sudah login
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login again.'
            ], 401);
        }

        $user = $request->user();

        if (!$user->role || $user->role != 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access Forbidden.'
            ], 403);
        }

        if (!Str::lower($user->status) == 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Status: ' . $user->status
            ], 403);
        }

        return $next($request);
    }
}
