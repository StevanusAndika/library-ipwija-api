<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register new user
     */
    public function register(RegisterRequest $request)
    {
        try {
            $data = $request->validated();

            $data['password'] = Hash::make($data['password']);
            $data['role'] = $data['role'] ?? 'user';
            $data['status'] = $data['status'] ?? 'PENDING';

            $user = User::create($data);

            $user = $user->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => $user->toApiResponse()
            ], 201);
        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $identifier = $request->input('email');
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

            $credentials = $isEmail
                ? ['email' => $identifier, 'password' => $request->password]
                : ['nim' => $identifier, 'password' => $request->password];

            $login = JWTAuth::attempt($credentials);

            if (!$login) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email/NIM atau Password salah. Silahkan coba lagi.'
                ], 401);
            }

            $user = $isEmail
                ? User::where('email', $identifier)->firstOrFail()
                : User::where('nim', $identifier)->firstOrFail();

            if (in_array($user->status, ['SUSPENDED', 'INACTIVE'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is deactivated'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $login,
                    'expires_in' => config('jwt.ttl') * 60,
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get current session
    public function me()
    {
        $user = Auth::user();
        $user->profile_picture = $user->profile_picture ? asset('storage/' . $user->profile_picture) : null;

        // Log login success
        Log::info('User logged in successfully', [
            'email' => $user->email,
            'role' => $user->role,
            'ip' => request()->ip()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);

    } 

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => $user->toApiResponse()
        ]);
    }

    public function logout()
    {
        try {
            $removeToken = JWTAuth::invalidate(JWTAuth::getToken());

            if($removeToken) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logout Berhasil!',  
                ]);
            }

            Auth::logout();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ==================== PASSWORD RESET WITH OTP ====================
     * OTP muncul langsung di response & matching dengan token
     */

    /**
 * FORGOT PASSWORD - VERSI SEDERHANA
 */
    public function forgotPassword(Request $request)
    {
        try {
            // Validasi
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;

            // Cek user
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Hapus token sebelumnya
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            // GENERATE OTP 6 DIGIT
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // GENERATE TOKEN untuk reset password
            $resetToken = Str::random(64);

            // Simpan ke database (SIMPLE VERSION)
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'otp' => Hash::make($otp),
                'token' => Hash::make($resetToken),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?: 'Unknown',
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
                'is_used' => false
            ]);

            // LOG untuk debugging
            Log::info('OTP Generated (Simple)', [
                'email' => $email,
                'otp' => $otp,
                'token' => $resetToken,
                'expires_at' => now()->addMinutes(15)->format('Y-m-d H:i:s')
            ]);

            // Response dengan OTP dan Token
            return response()->json([
                'success' => true,
                'message' => 'OTP generated successfully',
                'data' => [
                    'email' => $email,
                    'otp' => $otp,
                    'reset_token' => $resetToken,
                    'expires_in' => '15 minutes',
                    'expires_at' => now()->addMinutes(15)->format('Y-m-d H:i:s'),
                    'instructions' => 'Use this OTP and token with /api/reset-password endpoint'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
        /**
     * VERIFY OTP - Verify OTP saja
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6|regex:/^[0-9]+$/',
                'verification_code' => 'required|string' // Kode dari forgot-password response
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cari data reset berdasarkan email dan verification_code
            $resetData = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('verification_code', $request->verification_code)
                ->where('is_used', false)
                ->first();

            if (!$resetData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code or OTP already used'
                ], 400);
            }

            // Cek expired
            if (now()->greaterThan($resetData->expires_at)) {
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->update(['is_used' => true]);

                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            // Cek maksimal percobaan
            if ($resetData->attempts >= 3) {
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->update(['is_used' => true]);

                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new OTP.'
                ], 429);
            }

            // VERIFIKASI OTP
            if (!Hash::check($request->otp, $resetData->otp)) {
                // Increment failed attempts
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->increment('attempts');

                $remainingAttempts = 3 - ($resetData->attempts + 1);

                Log::warning('OTP verification failed', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'remaining_attempts' => $remainingAttempts
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            // OTP valid, tandai sebagai terverifikasi
            DB::table('password_reset_tokens')
                ->where('id', $resetData->id)
                ->update([
                    'verified_at' => now(),
                    'attempts' => $resetData->attempts + 1
                ]);

            // Generate verification token untuk step berikutnya
            $verificationToken = Str::random(32);

            // Simpan di cache untuk validasi reset password
            cache()->put('otp_verified:' . $verificationToken, [
                'email' => $request->email,
                'reset_id' => $resetData->id,
                'verification_code' => $request->verification_code,
                'verified_at' => now()
            ], now()->addMinutes(10));

            Log::info('OTP verified successfully', [
                'email' => $request->email,
                'reset_id' => $resetData->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'email' => $request->email,
                    'verification_token' => $verificationToken,
                    'valid_for_minutes' => 10,
                    'next_step' => 'Use this verification_token with /api/reset-password'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Verify OTP error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP'
            ], 500);
        }
    }

   /**
 * RESET PASSWORD - VERSI SEDERHANA (Tanpa verification_token)
 */
    public function resetPassword(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6|regex:/^[0-9]+$/',
                'reset_token' => 'required|string',
                'password' => 'required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // LOG untuk debugging
            Log::info('Reset Password Attempt:', [
                'email' => $request->email,
                'otp_received' => $request->otp,
                'token_received' => $request->reset_token
            ]);

            // Cari data reset di database
            $resetData = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('is_used', false)
                ->first();

            if (!$resetData) {
                Log::warning('Reset data not found', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'No active reset request found or already used'
                ], 400);
            }

            // LOG data dari database
            Log::info('Reset Data from DB:', [
                'db_otp_hash' => $resetData->otp,
                'db_token_hash' => $resetData->token,
                'expires_at' => $resetData->expires_at,
                'attempts' => $resetData->attempts,
                'is_used' => $resetData->is_used
            ]);

            // Cek apakah sudah expired
            if (now()->greaterThan($resetData->expires_at)) {
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->update(['is_used' => true]);

                Log::warning('OTP expired', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            // Cek maksimal percobaan
            if ($resetData->attempts >= 3) {
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->update(['is_used' => true]);

                Log::warning('Too many attempts', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new OTP.'
                ], 429);
            }

            // VERIFIKASI OTP
            if (!Hash::check($request->otp, $resetData->otp)) {
                // Increment failed attempts
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->increment('attempts');

                $remainingAttempts = 3 - ($resetData->attempts + 1);

                Log::warning('OTP verification failed', [
                    'email' => $request->email,
                    'remaining_attempts' => $remainingAttempts
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            // VERIFIKASI TOKEN
            if (!Hash::check($request->reset_token, $resetData->token)) {
                Log::warning('Token verification failed', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token'
                ], 400);
            }

            // Cari user
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Cek jika password sama dengan yang lama
            if (Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password cannot be the same as current password'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Tandai token sebagai digunakan
            DB::table('password_reset_tokens')
                ->where('id', $resetData->id)
                ->update([
                    'is_used' => true,
                    'used_at' => now()
                ]);

            // Hapus semua session/token user (force logout)
            $user->tokens()->delete();

            // Log keberhasilan
            Log::info('Password reset successful', [
                'email' => $user->email,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully',
                'data' => [
                    'email' => $user->email,
                    'name' => $user->name,
                    'reset_at' => now()->format('Y-m-d H:i:s'),
                    'note' => 'You have been logged out from all devices. Please login with new password.'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * SIMPLE RESET (tanpa OTP verification) - Untuk testing
     */
    public function simpleResetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'reset_token' => 'required|string',
                'password' => 'required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cari data reset
            $resetData = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('is_used', false)
                ->first();

            if (!$resetData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active reset request found'
                ], 400);
            }

            // Validasi token
            if (!Hash::check($request->reset_token, $resetData->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token'
                ], 400);
            }

            // Cek expired
            if (now()->greaterThan($resetData->expires_at)) {
                DB::table('password_reset_tokens')
                    ->where('id', $resetData->id)
                    ->update(['is_used' => true]);

                return response()->json([
                    'success' => false,
                    'message' => 'Reset token has expired'
                ], 400);
            }

            // Update password user
            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            // Tandai sebagai digunakan
            DB::table('password_reset_tokens')
                ->where('id', $resetData->id)
                ->update([
                    'is_used' => true,
                    'used_at' => now()
                ]);

            // Hapus semua token user
            $user->tokens()->delete();

            Log::info('Simple password reset successful', [
                'email' => $user->email,
                'method' => 'simple_reset'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully (simple method)',
                'data' => [
                    'email' => $user->email,
                    'reset_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Simple reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * RESEND OTP - Generate OTP & Token baru
     */
    public function resendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if ($user->status !== 'ACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not active'
                ], 403);
            }

            // Hapus OTP sebelumnya
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('is_used', false)
                ->delete();

            // Generate OTP baru
            $otp = $this->generateSecureOtp();
            $resetToken = Str::random(64);
            $verificationCode = $this->createVerificationCode($otp, $resetToken);

            // Simpan ke database
            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'otp' => Hash::make($otp),
                'token' => Hash::make($resetToken),
                'verification_code' => $verificationCode,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 255) : null,
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
                'is_used' => false
            ]);

            // Kirim email
            $this->sendOtpEmail($user, $otp);

            Log::info('OTP resent', [
                'email' => $request->email,
                'otp' => $otp
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New OTP generated successfully',
                'data' => [
                    'email' => $request->email,
                    'otp' => $otp,
                    'reset_token' => $resetToken,
                    'verification_code' => $verificationCode,
                    'expires_in' => '15 minutes',
                    'expires_at' => now()->addMinutes(15)->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Resend OTP error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP'
            ], 500);
        }
    }

    /**
     * ==================== HELPER METHODS ====================
     */

    /**
     * Generate secure OTP 6 digit
     */
    private function generateSecureOtp(): string
    {
        // Gunakan random_int untuk cryptographic security
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create verification code (kombinasi OTP + Token)
     */
    private function createVerificationCode(string $otp, string $token): string
    {
        // Kombinasi hash dari OTP dan token untuk uniqueness
        return hash('sha256', $otp . $token . time() . Str::random(16));
    }

    /**
     * Send OTP email
     */
    private function sendOtpEmail(User $user, string $otp): void
    {
        try {
            // Untuk production, uncomment ini:
            /*
            Mail::to($user->email)->send(new PasswordResetOtpMail(
                $user->name,
                $otp,
                now()->addMinutes(15)->format('H:i')
            ));
            */

            // Untuk development, log saja
            Log::channel('emails')->info('OTP Email would be sent', [
                'to' => $user->email,
                'name' => $user->name,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15)->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage(), [
                'email' => $user->email
            ]);
        }
    }

    /**
     * Send password changed notification
     */
    private function sendPasswordChangedNotification(User $user): void
    {
        try {
            Log::channel('emails')->info('Password changed notification would be sent', [
                'to' => $user->email,
                'name' => $user->name,
                'time' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send password changed notification: ' . $e->getMessage());
        }
    }

    /**
     * ==================== OTHER METHODS ====================
     */

    public function directResetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'reset_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    public function adminResetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'new_password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = $request->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can reset user password'
            ], 403);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Admins cannot reset their own password via this endpoint'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        Log::info('Password reset by admin', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reset_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User password reset successfully by admin',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'reset_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    public function completeMembership(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Only regular users can complete membership'
            ], 403);
        }

        if ($user->status === 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Membership already completed'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'tempat_lahir' => 'required|string|max:100',
            'tanggal_lahir' => 'required|date',
            'gender' => 'required|in:laki-laki,perempuan',
            'agama' => 'required|in:ISLAM,KRISTEN,HINDU,BUDDHA,KATOLIK,KONGHUCU',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:15',
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('profile_picture')) {
            $foto = $request->file('profile_picture');
            $fotoName = time() . '_' . $user->id . '.' . $foto->getClientOriginalExtension();
            $fotoPath = $foto->storeAs('user_photos', $fotoName, 'public');
            $user->profile_picture = $fotoPath;
        }

        $user->tempat_lahir = $request->tempat_lahir;
        $user->tanggal_lahir = $request->tanggal_lahir;
        $user->gender = $request->gender;
        $user->agama = $request->agama;
        $user->address = $request->address;
        $user->phone = $request->phone;
        $user->status = 'ACTIVE';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Membership completed successfully',
            'data' => $user->toApiResponse()
        ]);
    }
}
