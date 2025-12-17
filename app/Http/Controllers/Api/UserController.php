<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\ChangeUserStatusRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{

    public function check_uncomplete_data()
    {
        try {
            $user = Auth::user();

            $missingFields = [];
            if (!$user->nim) $missingFields[] = 'nim';
            if (!$user->phone) $missingFields[] = 'phone';
            if (!$user->alamat_asal && !$user->alamat_sekarang) $missingFields[] = 'alamat';
            if (!$user->tempat_lahir) $missingFields[] = 'tempat_lahir';
            if (!$user->tanggal_lahir) $missingFields[] = 'tanggal_lahir';
            if (!$user->agama) $missingFields[] = 'agama';

            if (count($missingFields) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lengkapi data-data berikut untuk menjadi anggota perpustakaan',
                    'data' => $missingFields
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Membership status set to complete',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check membership status',
                'error' => $e->getMessage()
            ], 500);
        }    
    }

    // Hanya boleh diakses oleh admin
    public function change_status_user(ChangeUserStatusRequest $request)
    {
        try {
            $user = Auth::user();
            $id_user = $request->input('user_id');

            if ($user->id == $id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat mengubah status diri sendiri.'
                ], 403);
            }

            $targetUser = User::find($id_user);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan.'
                ], 404);
            }

            $targetUser->status = $request->input('status');
            $targetUser->save();

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $targetUser
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
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

        // Filter by membership status
        if ($request->has('is_anggota')) {
            $query->where('is_anggota', $request->boolean('is_anggota'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Add statistics
        $query->withCount([
            'borrowings',
            'activeBorrowings as active_borrowings_count',
            'unpaidFines as unpaid_fines_count'
        ]);

        $perPage = $request->get('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    public function show($id)
    {
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

    public function update(UpdateUserRequest $request, $id = null)
    {
        if (!$id) {
            $id = Auth::user()->id;
        }
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        $data = $request->except(['foto', 'password']);

        // Update password if provided
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('foto')) {
            // Delete old photo if exists
            if ($user->foto && Storage::disk('public')->exists($user->foto)) {
                Storage::disk('public')->delete($user->foto);
            }

            $foto = $request->file('foto');
            $fotoName = time() . '_' . $user->id . '.' . $foto->getClientOriginalExtension();
            $fotoPath = $foto->storeAs('user_photos', $fotoName, 'public');
            $data['foto'] = $fotoPath;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // Prevent admin from deleting themselves
        if ($user->id === Auth::user()->id) {
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
        if ($user->foto && Storage::disk('public')->exists($user->foto)) {
            Storage::disk('public')->delete($user->foto);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "User {$status} successfully",
            'data' => $user
        ]);
    }

    public function toggleMembership($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' => 'Only mahasiswa can have membership'
            ], 400);
        }

        $user->is_anggota = !$user->is_anggota;
        $user->save();

        $status = $user->is_anggota ? 'granted membership' : 'revoked membership';

        return response()->json([
            'success' => true,
            'message' => "User {$status} successfully",
            'data' => $user
        ]);
    }

    public function getUserStats($id)
    {
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
