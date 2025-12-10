<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Admin: Get all users
    public function index(Request $request)
    {
        // TAMBAHKAN PENGEECEKAN ADMIN DI SINI
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by gender
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        // Add statistics
        $query->withCount([
            'borrowings',
            'activeBorrowings as active_borrowings_count',
            'unpaidFines as unpaid_fines_count'
        ]);

        $perPage = $request->get('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform response
        $users->getCollection()->transform(function ($user) {
            return $user->toApiResponse();
        });

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    // TAMBAHKAN JUGA DI METHOD LAIN YANG HARUS ADMIN-ONLY
    public function show($id)
    {
        // Cek admin
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $user = User::withCount([
            'borrowings',
            'activeBorrowings',
            'fines',
            'unpaidFines'
        ])->with([
            'activeBorrowings' => function($query) {
                $query->with(['book.category'])->limit(5);
            },
            'unpaidFines' => function($query) {
                $query->with(['borrowing.book'])->limit(5);
            }
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user->toApiResponse()
        ]);
    }

    public function update(Request $request, $id)
    {
        // Cek admin
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'nim' => 'sometimes|string|unique:users,nim,' . $id,
            'phone' => 'sometimes|string|max:15',
            'role' => 'sometimes|in:admin,user',
            'tempat_lahir' => 'sometimes|string|max:100',
            'tanggal_lahir' => 'sometimes|date',
            'gender' => 'sometimes|in:laki-laki,perempuan',
            'agama' => 'sometimes|in:ISLAM,KRISTEN,HINDU,BUDDHA,KATOLIK,KONGHUCU',
            'address' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:PENDING,ACTIVE,SUSPENDED,INACTIVE',
            'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'sometimes|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['profile_picture', 'password']);

        // Update password if provided
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Handle photo upload
        if ($request->hasFile('profile_picture')) {
            // Delete old photo if exists
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $foto = $request->file('profile_picture');
            $fotoName = time() . '_' . $user->id . '.' . $foto->getClientOriginalExtension();
            $fotoPath = $foto->storeAs('user_photos', $fotoName, 'public');
            $data['profile_picture'] = $fotoPath;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->toApiResponse()
        ]);
    }

    public function destroy($id)
    {
        // Cek admin
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // Prevent admin from deleting themselves
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Check if user has active borrowings
        if ($user->activeBorrowings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user with active borrowings'
            ], 400);
        }

        // Check if user has unpaid fines
        if ($user->unpaidFines()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user with unpaid fines'
            ], 400);
        }

        // Delete user photo if exists
        if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    public function toggleStatus($id)
    {
        // Cek admin
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // Toggle between ACTIVE and INACTIVE
        $user->status = $user->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $user->save();

        $status = $user->status === 'ACTIVE' ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "User {$status} successfully",
            'data' => $user->toApiResponse()
        ]);
    }

    public function getUserStats($id)
    {
        // Cek admin
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => [
                'user' => $user->toApiResponse(),
                'stats' => $user->getStats()
            ]
        ]);
    }
}
