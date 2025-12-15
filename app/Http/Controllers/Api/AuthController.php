<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
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
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
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
                    'message' => 'Invalid credentials'
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

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

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

    public function forgotPassword(Request $request)
    {
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

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json([
                'success' => true,
                'message' => 'Reset link sent to your email'
            ])
            : response()->json([
                'success' => false,
                'message' => 'Unable to send reset link'
            ], 400);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json([
                'success' => true,
                'message' => 'Password reset successful'
            ])
            : response()->json([
                'success' => false,
                'message' => 'Unable to reset password'
            ], 400);
    }

    // Complete membership for mahasiswa
    public function completeMembership(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' => 'Only mahasiswa can complete membership'
            ], 403);
        }

        if ($user->is_anggota) {
            return response()->json([
                'success' => false,
                'message' => 'Membership already completed'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'tempat_lahir' => 'required|string|max:100',
            'tanggal_lahir' => 'required|date',
            'agama' => 'required|string|max:50',
            'alamat_asal' => 'required|string|max:500',
            'alamat_sekarang' => 'required|string|max:500',
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload foto
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto');
            $fotoName = time() . '_' . $user->id . '.' . $foto->getClientOriginalExtension();
            $fotoPath = $foto->storeAs('user_photos', $fotoName, 'public');

            $user->foto = $fotoPath;
        }

        // Update user data
        $user->tempat_lahir = $request->tempat_lahir;
        $user->tanggal_lahir = $request->tanggal_lahir;
        $user->agama = $request->agama;
        $user->alamat_asal = $request->alamat_asal;
        $user->alamat_sekarang = $request->alamat_sekarang;
        $user->is_anggota = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Membership completed successfully',
            'data' => $user->toApiResponse()
        ]);
    }
}
