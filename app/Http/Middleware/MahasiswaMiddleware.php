<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MahasiswaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
                'data' => null
            ], 401);
        }

        if (auth()->user()->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Mahasiswa access required.',
                'data' => null
            ], 403);
        }

        if (!auth()->user()->is_anggota) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete your library membership first.',
                'data' => null
            ], 403);
        }

        return $next($request);
    }
}
