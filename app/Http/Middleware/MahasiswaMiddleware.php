<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class MahasiswaMiddleware
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

        if (!$user->isMahasiswa()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Mahasiswa access only.'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated.'
            ], 403);
        }

        // Check if mahasiswa has completed membership (if endpoint requires it)
        $requiresMembership = $this->requiresMembership($request);
        if ($requiresMembership && !$user->is_anggota) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete your membership data first.'
            ], 403);
        }

        return $next($request);
    }

    private function requiresMembership(Request $request): bool
    {
        // List of endpoints that require membership
        $membershipRequired = [
            'borrowings',
            'borrowings/*/extend',
            'books/*/download'
        ];

        $path = $request->path();

        foreach ($membershipRequired as $pattern) {
            if (str_contains($pattern, '*')) {
                $pattern = str_replace('*', '.*', $pattern);
                if (preg_match('#^' . $pattern . '$#', $path)) {
                    return true;
                }
            } elseif ($path === $pattern) {
                return true;
            }
        }

        return false;
    }
}
