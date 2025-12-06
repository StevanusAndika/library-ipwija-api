<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;


class DashboardController extends Controller
{
    public function getStats()
    {
        $totalBooks = Book::count();
        $totalAvailableBooks = Book::where('is_active', true)->sum('available_stock');
        $totalCategories = Category::count();
        $totalUsers = User::where('role', 'mahasiswa')->count();
        $totalActiveBorrowings = Borrowing::whereIn('status', ['approved', 'borrowed', 'late'])->count();
        $totalPendingBorrowings = Borrowing::where('status', 'pending')->count();
        $totalUnpaidFines = Fine::where('status', 'unpaid')->sum('amount');

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $monthlyBorrowings = Borrowing::whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        $monthlyReturns = Borrowing::whereYear('return_date', $currentYear)
            ->whereMonth('return_date', $currentMonth)
            ->count();

        $topBooks = Borrowing::select('book_id', DB::raw('COUNT(*) as borrow_count'))
            ->with('book')
            ->groupBy('book_id')
            ->orderBy('borrow_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard statistics retrieved',
            'data' => [
                'total_books' => $totalBooks,
                'total_available_books' => $totalAvailableBooks,
                'total_categories' => $totalCategories,
                'total_users' => $totalUsers,
                'total_active_borrowings' => $totalActiveBorrowings,
                'total_pending_borrowings' => $totalPendingBorrowings,
                'total_unpaid_fines' => $totalUnpaidFines,
                'monthly_borrowings' => $monthlyBorrowings,
                'monthly_returns' => $monthlyReturns,
                'top_books' => $topBooks,
            ]
        ]);
    }

   public function getUsers(Request $request)
    {
        try {
            // Pastikan user adalah admin
            if ($request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. Admin access required.',
                    'data' => null
                ], 403);
            }

            $query = \App\Models\User::where('role', 'mahasiswa'); // Hanya mahasiswa

            // Filter by search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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

            // Pagination - SEMENTARA TANPA WITHCOUNT
            $perPage = $request->get('per_page', 15);
            $users = $query->select([
                'id', 'name', 'email', 'nim', 'phone', 'role',
                'is_anggota', 'is_active', 'created_at', 'updated_at'
            ])->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getUsers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
 * Update user data
 */
public function updateUser(Request $request, $id)
{
    // Cari user
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User tidak ditemukan'
        ], 404);
    }

    // Authorization
    $currentUser = auth()->user();
    if ($currentUser->id != $user->id && $currentUser->role != 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    // Validasi
    $validator = Validator::make($request->all(), [
        'name' => ['sometimes', 'string', 'max:255'],
        'email' => [
            'sometimes',
            'email',
            'max:255',
            Rule::unique('users')->ignore($user->id)
        ],
        'phone' => ['sometimes', 'string', 'max:20', 'nullable'],
        'address' => ['sometimes', 'string', 'max:500', 'nullable'],
        'membership_type' => ['sometimes', 'string', 'in:regular,premium,vip'],
        'membership_expiry' => ['sometimes', 'date', 'after_or_equal:today', 'nullable'],
        'is_active' => ['sometimes', 'boolean'],
        'password' => ['sometimes', 'string', 'min:8'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    // Update data
    if ($request->has('name')) {
        $user->name = $request->name;
    }

    if ($request->has('email')) {
        $user->email = $request->email;
    }

    if ($request->has('phone')) {
        $user->phone = $request->phone;
    }

    if ($request->has('address')) {
        $user->address = $request->address;
    }

    if ($request->has('membership_type')) {
        $user->membership_type = $request->membership_type;
    }

    if ($request->has('membership_expiry')) {
        $user->membership_expiry = $request->membership_expiry;
    }

    // Hanya admin yang bisa update is_active
    if ($request->has('is_active') && $currentUser->role == 'admin') {
        $user->is_active = $request->is_active;
    }

    // Update password
    if ($request->has('password')) {
        // Hanya bisa update password sendiri kecuali admin
        if ($currentUser->role != 'admin' && $currentUser->id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah password user lain'
            ], 403);
        }
        $user->password = Hash::make($request->password);
    }

    // Simpan perubahan
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'User berhasil diupdate',
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'membership_type' => $user->membership_type,
            'membership_expiry' => $user->membership_expiry,
            'is_active' => $user->is_active,
            'role' => $user->role,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]
    ], 200);
}

    public function toggleUserStatus($id)
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
}
