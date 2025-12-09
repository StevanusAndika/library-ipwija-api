<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BorrowingController;
use App\Http\Controllers\Api\FineController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ==================== PUBLIC ROUTES ====================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public book routes (tanpa login)
Route::get('/books', [BookController::class, 'indexPublic']);
Route::get('/books/{id}', [BookController::class, 'showPublic']);

// Public category routes
Route::get('/categories', [CategoryController::class, 'indexPublic']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/categories/{id}/books', [CategoryController::class, 'booksByCategory']);

// ==================== AUTHENTICATED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {
    // ========== AUTH ROUTES ==========
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/complete-membership', [AuthController::class, 'completeMembership']);

    // ========== MAHASISWA ROUTES ==========
    Route::middleware('mahasiswa')->group(function () {
        // Borrowing
        Route::get('/my-borrowings', [BorrowingController::class, 'myBorrowings']);
        Route::get('/borrowing-history', [BorrowingController::class, 'borrowingHistory']);
        Route::post('/borrowings', [BorrowingController::class, 'store']);
        Route::post('/borrowings/{id}/extend', [BorrowingController::class, 'extend']);
        Route::get('/check-borrow-status', [BorrowingController::class, 'checkBorrowStatus']);
        // Fines
        Route::get('/my-fines', [FineController::class, 'myFines']);

        // Ebook download
        Route::get('/books/{id}/download', [BookController::class, 'downloadEbook']);
    });

    // ========== ADMIN ROUTES ==========
    Route::middleware('admin')->prefix('admin')->group(function () {
        // ===== DASHBOARD =====
        Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
        Route::get('/dashboard/chart-data', [DashboardController::class, 'getChartData']);

        // ===== USER MANAGEMENT =====
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('/users/{id}/toggle-membership', [UserController::class, 'toggleMembership']);
        Route::get('/users/{id}/stats', [UserController::class, 'getUserStats']);

        // ===== CATEGORY MANAGEMENT =====
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // ===== BOOK MANAGEMENT =====
        Route::get('/books', [BookController::class, 'index']);
        Route::post('/books', [BookController::class, 'store']);
        Route::get('/books/{id}', [BookController::class, 'show']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);

        // ===== BORROWING MANAGEMENT =====
        Route::get('/borrowings', [BorrowingController::class, 'index']);
        Route::get('/borrowings/{id}', [BorrowingController::class, 'show']);
        Route::post('/borrowings/{id}/approve', [BorrowingController::class, 'approve']);
        Route::post('/borrowings/{id}/reject', [BorrowingController::class, 'reject']);
        Route::post('/borrowings/{id}/mark-borrowed', [BorrowingController::class, 'markAsBorrowed']);
        Route::post('/borrowings/{id}/return', [BorrowingController::class, 'returnBook']);

        // ===== BORROWING REPORTS =====
        Route::get('/currently-borrowed', [BorrowingController::class, 'currentlyBorrowed']);
        Route::get('/late-returns', [BorrowingController::class, 'lateReturns']);
        Route::get('/unpaid-fines', [BorrowingController::class, 'unpaidFines']);

        // ===== FINE MANAGEMENT =====
        Route::get('/fines', [FineController::class, 'index']);
        Route::post('/fines/{id}/mark-paid', [FineController::class, 'markAsPaid']);
        Route::get('/fines/statistics', [FineController::class, 'statistics']);
    });
});

// ==================== FALLBACK ROUTE ====================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found. Check API documentation.',
        'available_endpoints' => [
            'POST /api/register',
            'POST /api/login',
            'POST /api/logout',
            'GET /api/profile',
            'GET /api/books',
            'GET /api/categories',
            'GET /api/my-borrowings',
            'GET /api/my-fines',
            'POST /api/complete-membership',
            'GET /api/admin/dashboard/stats',
            'GET /api/admin/users',
            'GET /api/admin/categories',
            'GET /api/admin/books',
            'GET /api/admin/borrowings',
            'GET /api/admin/fines',
        ]
    ], 404);
});
