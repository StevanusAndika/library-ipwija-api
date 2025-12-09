<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        // Total books - GANTI is_active menjadi status (Book sudah ganti)
        $totalBooks = Book::count();
        $availableBooks = Book::where('status', 1)->sum('available_stock');

        // Total categories - GANTI is_active menjadi status (Category sudah ganti)
        $totalCategories = Category::count();
        $activeCategories = Category::where('status', 1)->count();

        // Total users - TETAP is_active (User masih pakai is_active)
        $totalUsers = User::where('role', 'mahasiswa')->count();
        $activeUsers = User::where('role', 'mahasiswa')->where('is_active', true)->count();
        $anggotaUsers = User::where('role', 'mahasiswa')->where('is_anggota', true)->count();

        // Borrowing stats
        $totalBorrowings = Borrowing::count();
        $pendingBorrowings = Borrowing::where('status', 'pending')->count();
        $activeBorrowings = Borrowing::whereIn('status', ['approved', 'borrowed'])->count();
        $lateBorrowings = Borrowing::where('status', 'late')->count();

        // Fine stats
        $totalFines = Fine::count();
        $unpaidFines = Fine::where('status', 'unpaid')->sum('amount');
        $paidFines = Fine::where('status', 'paid')->sum('amount');

        // Today stats
        $today = now()->format('Y-m-d');
        $borrowingsToday = Borrowing::whereDate('created_at', $today)->count();
        $returnsToday = Borrowing::whereDate('return_date', $today)->count();

        // This month stats
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $borrowingsThisMonth = Borrowing::whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)->count();
        $returnsThisMonth = Borrowing::whereYear('return_date', $currentYear)
            ->whereMonth('return_date', $currentMonth)->count();

        // Top borrowed books - Hanya buku aktif
        $topBooks = Borrowing::select('book_id', DB::raw('COUNT(*) as borrow_count'))
            ->with(['book' => function($query) {
                $query->where('status', 1);
            }])
            ->whereHas('book', function($query) {
                $query->where('status', 1);
            })
            ->groupBy('book_id')
            ->orderBy('borrow_count', 'desc')
            ->limit(5)
            ->get();

        // Top borrowers
        $topBorrowers = Borrowing::select('user_id', DB::raw('COUNT(*) as borrow_count'))
            ->with('user')
            ->groupBy('user_id')
            ->orderBy('borrow_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => [
                'books' => [
                    'total' => $totalBooks,
                    'available' => $availableBooks
                ],
                'categories' => [
                    'total' => $totalCategories,
                    'active' => $activeCategories
                ],
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'anggota' => $anggotaUsers
                ],
                'borrowings' => [
                    'total' => $totalBorrowings,
                    'pending' => $pendingBorrowings,
                    'active' => $activeBorrowings,
                    'late' => $lateBorrowings,
                    'today' => $borrowingsToday,
                    'this_month' => $borrowingsThisMonth,
                    'returns_today' => $returnsToday,
                    'returns_this_month' => $returnsThisMonth
                ],
                'fines' => [
                    'total' => $totalFines,
                    'unpaid_amount' => $unpaidFines,
                    'paid_amount' => $paidFines
                ],
                'top_books' => $topBooks,
                'top_borrowers' => $topBorrowers
            ]
        ]);
    }

    public function getChartData(Request $request)
    {
        $year = $request->get('year', now()->year);

        // Monthly borrowing data
        $monthlyBorrowings = Borrowing::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month');

        // Monthly return data
        $monthlyReturns = Borrowing::selectRaw('MONTH(return_date) as month, COUNT(*) as count')
            ->whereYear('return_date', $year)
            ->whereNotNull('return_date')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month');

        // Category distribution - Hanya buku aktif
        $categoryDistribution = Book::select('category_id', DB::raw('COUNT(*) as count'))
            ->where('status', 1) // Book: status bukan is_active
            ->with('category')
            ->groupBy('category_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Book type distribution - Hanya buku aktif
        $bookTypeDistribution = Book::select('book_type', DB::raw('COUNT(*) as count'))
            ->where('status', 1) // Book: status bukan is_active
            ->groupBy('book_type')
            ->get();

        // Fine status distribution
        $fineDistribution = Fine::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Chart data retrieved successfully',
            'data' => [
                'monthly_borrowings' => $monthlyBorrowings,
                'monthly_returns' => $monthlyReturns,
                'category_distribution' => $categoryDistribution,
                'book_type_distribution' => $bookTypeDistribution,
                'fine_distribution' => $fineDistribution
            ]
        ]);
    }
}
