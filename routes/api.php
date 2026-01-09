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

// ==================== TEST ROUTE UNTUK RATE LIMITING ====================


// ==================== PUBLIC ROUTES ====================
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome to the Library Management System API',
        'version' => '1.0.0'
    ]);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Auth routes dengan rate limiting KETAT
Route::post('/register', [AuthController::class, 'register']); // 5 requests per 10 menit

Route::post('/login', [AuthController::class, 'login']);

// Password reset routes (public) dengan rate limiting SANGAT KETAT
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::post('/direct-reset-password', [AuthController::class, 'directResetPassword']);

Route::post('/simple-reset-password', [AuthController::class, 'simpleResetPassword']);

// Public book routes dengan rate limiting STANDARD
Route::prefix('books')->group(function () {
    Route::get('/', [BookController::class, 'indexPublic']);
    Route::get('{id}', [BookController::class, 'showPublic']);
});


// Public category routes dengan rate limiting STANDARD
Route::prefix('categories')->group(function () {

    Route::get('/', [CategoryController::class, 'indexPublic']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}/books', [CategoryController::class, 'booksByCategory']);
});

// ==================== PROTECTED ROUTES (JWT) ====================
Route::middleware('auth:api')->group(function () {

    Route::prefix('profile')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/update/{id?}', [UserController::class, 'update']);
    });

    Route::middleware('user')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dashboard_user']);
        Route::get('/check-uncomplete-data', [UserController::class, 'check_uncomplete_data']);
    });

    Route::middleware('admin')->prefix('admin')->group(function () {
        // ===== DASHBOARD =====
        Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
        Route::get('/dashboard/chart-data', [DashboardController::class, 'getChartData']);

        // ===== USER MANAGEMENT =====
        Route::prefix('/users')->group(function () { 
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            
            Route::post('/batch-insert', [UserController::class, 'batch_insert_users']);
            Route::post('/change-status-user', [UserController::class, 'change_status_user']);
            
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id?}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            // Route::post('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
            Route::post('/{id}/toggle-membership', [UserController::class, 'toggleMembership']);
            Route::get('/{id}/stats', [UserController::class, 'getUserStats']);
        });

        // Admin password reset - rate limiting EXTRA KETAT
        Route::post('/admin-reset-password', [AuthController::class, 'adminResetPassword'])
            ->middleware('rate.limit:5,10'); // 5 requests per 10 menit

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

        // Admin actions dengan rate limiting KETAT
        Route::middleware('rate.limit:30,1')->group(function () {
            Route::post('/borrowings/{id}/approve', [BorrowingController::class, 'approve']);
            Route::post('/borrowings/{id}/reject', [BorrowingController::class, 'reject']);
            Route::post('/borrowings/{id}/mark-borrowed', [BorrowingController::class, 'markAsBorrowed']);
            Route::post('/borrowings/{id}/return', [BorrowingController::class, 'returnBook']);
            Route::post('/borrowings/{id}/update-status', [BorrowingController::class, 'updateStatus']);
            Route::post('/borrowings/{id}/generate-fine', [BorrowingController::class, 'generateFineManually']);
            Route::post('/borrowings/{id}/mark-late', [BorrowingController::class, 'markAsLate']);
            Route::post('/borrowings/{id}/mark-fine-paid', [BorrowingController::class, 'markFinePaid']);
            Route::post('/fines/{id}/mark-paid', [FineController::class, 'markAsPaid']);
        });

        // ===== BORROWING REPORTS =====
        Route::get('/currently-borrowed', [BorrowingController::class, 'currentlyBorrowed']);
        Route::get('/late-returns', [BorrowingController::class, 'lateReturns']);
        Route::get('/unpaid-fines', [BorrowingController::class, 'unpaidFines']);

        // Admin tools dengan rate limiting KHUSUS
        Route::post('/borrowings/auto-check-overdue', [BorrowingController::class, 'autoCheckOverdue'])
            ->middleware('rate.limit:10,5'); // 10 requests per 5 menit

        Route::get('/borrowings/needing-update', [BorrowingController::class, 'getBorrowingsNeedingUpdate']);

        // ===== FINE MANAGEMENT =====
        Route::get('/fines', [FineController::class, 'index']);
        Route::get('/fines/statistics', [FineController::class, 'statistics']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});

// ==================== AUTHENTICATED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {
    // ========== AUTH ROUTES ==========
    Route::get('/profile', [AuthController::class, 'profile']);
    
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
    
});

// ==================== FALLBACK ROUTE ====================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found. Check API documentation.',
        'available_endpoints' => [
            'PUBLIC ENDPOINTS:',
            'GET  /api/test-rate-limit (rate limited: 60/min)',
            'POST /api/register (rate limited: 5/10min)',
            'POST /api/login (rate limited: 10/5min)',
            'POST /api/forgot-password (rate limited: 3/15min)',
            'POST /api/reset-password (rate limited: 3/15min)',
            'POST /api/direct-reset-password (rate limited: 3/15min)',
            'POST /api/simple-reset-password (rate limited: 3/15min)',
            'GET  /api/books (rate limited: 30/min)',
            'GET  /api/categories (rate limited: 30/min)',

            'AUTHENTICATED ENDPOINTS:',
            'GET  /api/profile (rate limited: 60/min)',
            'POST /api/logout (rate limited: 60/min)',
            'POST /api/complete-membership (rate limited: 60/min)',

            'USER ENDPOINTS:',
            'GET  endpoints (rate limited: 120/min)',
            'POST endpoints (rate limited: 30/min)',
            'GET  /api/books/{id}/download (rate limited: 10/5min)',

            'ADMIN ENDPOINTS:',
            'Most endpoints (rate limited: 100/min)',
            'Critical actions (rate limited: 30/min)',
            'Admin password reset (rate limited: 5/10min)',
            'Auto check overdue (rate limited: 10/5min)'
        ]
    ], 404);
});
