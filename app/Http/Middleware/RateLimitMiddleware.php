<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Log;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Middleware ini bertugas membatasi jumlah request yang bisa dilakukan
     * dalam periode waktu tertentu untuk mencegah DDoS dan abuse resource.
     *
     * @param Request $request Request dari client
     * @param Closure $next Fungsi untuk melanjutkan ke middleware/controller berikutnya
     * @param int $maxAttempts Jumlah maksimum request yang diizinkan (default: 60)
     * @param int $decayMinutes Rentang waktu dalam menit (default: 1 menit)
     * @return Response Response HTTP
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        // 1. BUAT IDENTIFIER UNIK UNTUK SETIAP CLIENT
        // Key ini digunakan untuk melacak berapa banyak request yang sudah dilakukan
        $key = $this->resolveRequestSignature($request);

        // Konversi parameter ke integer untuk keamanan
        $maxAttempts = (int) $maxAttempts;
        $decayMinutes = (int) $decayMinutes;

        // 2. CEK APAKAH CLIENT SUDAH MELEBIHI BATAS REQUEST
        // RateLimiter::tooManyAttempts() mengecek apakah key sudah mencapai limit
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            // Jika melebihi limit, hitung berapa detik lagi client harus menunggu
            $retryAfter = RateLimiter::availableIn($key);

            // 3. LOG KEJADIAN RATE LIMIT EXCEEDED (untuk monitoring dan analisis)
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),              // IP address client
                'user_agent' => $request->userAgent(), // Browser/device client
                'url' => $request->fullUrl(),        // URL yang diakses
                'retry_after' => $retryAfter         // Waktu tunggu dalam detik
            ]);

            // 4. RETURN RESPONSE ERROR 429 (TOO MANY REQUESTS)
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,        // Informasi waktu tunggu
                'max_attempts' => $maxAttempts,      // Batas maksimum request
                'decay_minutes' => $decayMinutes     // Periode waktu
            ], 429)->withHeaders([                   // HTTP Status Code 429
                'Retry-After' => $retryAfter,        // Header standar untuk rate limiting
                'X-RateLimit-Limit' => $maxAttempts, // Custom header: batas maksimum
                'X-RateLimit-Remaining' => 0,        // Custom header: sisa request = 0
            ]);
        }

        // 5. JIKA BELUM MELEBIHI BATAS, TAMBAH COUNTER
        // RateLimiter::hit() menambah hit counter untuk key tersebut
        // Parameter kedua adalah waktu kedaluwarsa dalam detik
        RateLimiter::hit($key, $decayMinutes * 60);

        // 6. LANJUTKAN KE REQUEST BERIKUTNYA
        $response = $next($request);

        // 7. TAMBAHKAN HEADER INFORMASI RATE LIMIT PADA RESPONSE
        // Header ini membantu client mengetahui status rate limit mereka
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,                   // Batas maksimum
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts), // Sisa request
        ]);

        return $response;
    }

    /**
     * Resolve request signature.
     *
     * Method ini membuat identifier unik berdasarkan:
     * 1. Jika user sudah login: user_id + IP + path
     * 2. Jika user belum login: IP + User Agent + path
     *
     * Menggunakan sha1() untuk menghasilkan hash yang konsisten dan aman.
     *
     * @param Request $request Request dari client
     * @return string Signature/identifier unik
     */
    protected function resolveRequestSignature(Request $request): string
    {
        // Jika user sudah login (authenticated), gunakan user ID
        if ($user = $request->user()) {
            // Format: user_id|ip_address|request_path
            // Contoh: 123|192.168.1.1|/api/books
            // Hash: sha1("123|192.168.1.1|/api/books") = "a1b2c3..."
            return sha1($user->id . '|' . $request->ip() . '|' . $request->path());
        }

        // Jika user belum login, gunakan IP + User Agent
        // User Agent membantu membedakan browser/device berbeda dari IP yang sama
        return sha1($request->ip() . '|' . $request->userAgent() . '|' . $request->path());
    }
}
