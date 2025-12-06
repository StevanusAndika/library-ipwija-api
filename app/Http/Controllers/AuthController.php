<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'nim' => 'required|string|max:20|unique:users',
            'phone' => 'required|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nim' => $request->nim,
            'phone' => $request->phone,
            'role' => 'mahasiswa',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'data' => null
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated',
                'data' => null
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request)
{
    try {
        // Cek jika user sudah login
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated user',
                'data' => null
            ], 401);
        }

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
            'data' => null
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Logout failed: ' . $e->getMessage(),
            'data' => null
        ], 500);
    }
}

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'User data retrieved',
            'data' => $request->user()
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $resetToken = Str::random(60);
        $user->remember_token = $resetToken;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to email',
            'data' => [
                'reset_token' => $resetToken
            ]
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('remember_token', $request->token)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token',
                'data' => null
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->remember_token = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
            'data' => null
        ]);
    }

    public function updateMembership(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'tempat_lahir' => 'required|string|max:100',
            'tanggal_lahir' => 'required|date',
            'agama' => 'required|string|max:50',
            'alamat_asal' => 'required|string',
            'alamat_sekarang' => 'required|string',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('foto_anggota', $filename, 'public');
            $user->foto = $path;
        }

        $user->tempat_lahir = $request->tempat_lahir;
        $user->tanggal_lahir = $request->tanggal_lahir;
        $user->agama = $request->agama;
        $user->alamat_asal = $request->alamat_asal;
        $user->alamat_sekarang = $request->alamat_sekarang;
        $user->is_anggota = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Membership data updated successfully',
            'data' => $user
        ]);
    }
}
