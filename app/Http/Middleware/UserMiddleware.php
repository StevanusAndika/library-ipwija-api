<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class UserMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.'
            ], 401);
        }

        $user = Auth::user();

        // FIX: Sesuaikan dengan struktur baru (role 'user' bukan 'mahasiswa')
        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Regular user access only.'
            ], 403);
        }

        // FIX: Gunakan status bukan is_active
        if ($user->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Status: ' . $user->status
            ], 403);
        }

        return $next($request);
    }
}
