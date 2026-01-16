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
Route::post('/direct-reset-password', [AuthController::class, 'directResetPassword']);
Route::post('/simple-reset-password', [AuthController::class, 'simpleResetPassword']);

// Public book routes
Route::prefix('books')->group(function () {
    Route::get('/', [BookController::class, 'indexPublic']);
    Route::get('/{id}', [BookController::class, 'showPublic']);
});

// Public category routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'indexPublic']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}/books', [CategoryController::class, 'booksByCategory']);
});

// ==================== PROTECTED ROUTES (JWT) ====================
Route::middleware('auth:api')->group(function () {
    // ========== AUTH ROUTES ==========
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/complete-membership', [AuthController::class, 'completeMembership']);

    Route::prefix('profile')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/update/{id?}', [UserController::class, 'update']);
    });

    // ========== USER/MAHASISWA ROUTES ==========
    Route::middleware('user')->group(function () {
        // User dashboard
        Route::get('/dashboard', [DashboardController::class, 'dashboard_user']);
        Route::get('/check-uncomplete-data', [UserController::class, 'check_uncomplete_data']);

        // Fines routes
        Route::get('/my-fines', [FineController::class, 'myFines']);

        // Ebook download
        Route::get('/books/{id}/download', [BookController::class, 'downloadEbook']);
    });

    // ========== BORROWING ROUTES (untuk semua authenticated users) ==========
    Route::prefix('borrowings')->group(function () {
        Route::get('/my-borrowings', [BorrowingController::class, 'myBorrowings']);
        Route::get('/borrowing-history', [BorrowingController::class, 'borrowingHistory']);
        Route::post('/', [BorrowingController::class, 'store']);
        Route::get('/check-borrow-status', [BorrowingController::class, 'checkBorrowStatus']);
        Route::get('/my-stats', [BorrowingController::class, 'myStats']);
        Route::get('/check-book-status/{bookId}', [BorrowingController::class, 'checkBookBorrowStatus']);

        // Routes dengan ID
        Route::prefix('{id}')->group(function () {
            Route::get('/details', [BorrowingController::class, 'getBorrowingDetails']);
            Route::post('/extend', [BorrowingController::class, 'extend']);
            Route::post('/cancel', [BorrowingController::class, 'cancel']);
        });
    });

    // ========== ADMIN ROUTES ==========
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
            Route::post('/{id}/toggle-membership', [UserController::class, 'toggleMembership']);
            Route::get('/{id}/stats', [UserController::class, 'getUserStats']);
        });

        // Admin password reset
        Route::post('/admin-reset-password', [AuthController::class, 'adminResetPassword']);

        // ===== CATEGORY MANAGEMENT =====
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::get('/{id}', [CategoryController::class, 'show']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
        });

        // ===== BOOK MANAGEMENT =====
        Route::prefix('books')->group(function () {
            Route::get('/', [BookController::class, 'index']);
            Route::post('/', [BookController::class, 'store']);
            Route::get('/{id}', [BookController::class, 'show']);
            Route::put('/{id}', [BookController::class, 'update']);
            Route::delete('/{id}', [BookController::class, 'destroy']);
        });

        // ===== BORROWING MANAGEMENT =====
        Route::prefix('borrowings')->group(function () {
            Route::get('/', [BorrowingController::class, 'index']);
            Route::get('/{id}', [BorrowingController::class, 'show']);
        });

        // Admin actions dengan rate limiting KETAT
        Route::middleware('throttle:30,1')->group(function () {
            Route::prefix('borrowings')->group(function () {
                Route::post('/{id}/approve', [BorrowingController::class, 'approve']);
                Route::post('/{id}/reject', [BorrowingController::class, 'reject']);
                Route::post('/{id}/mark-borrowed', [BorrowingController::class, 'markAsBorrowed']);
                Route::post('/{id}/return', [BorrowingController::class, 'returnBook']);
                Route::post('/{id}/update-status', [BorrowingController::class, 'updateStatus']);
                Route::post('/{id}/generate-fine', [BorrowingController::class, 'generateFineManually']);
                Route::post('/{id}/mark-late', [BorrowingController::class, 'markAsLate']);
                Route::post('/{id}/mark-fine-paid', [BorrowingController::class, 'markFinePaid']);
            });

            Route::prefix('fines')->group(function () {
                Route::post('/{id}/mark-paid', [FineController::class, 'markAsPaid']);
            });
        });

        // ===== BORROWING REPORTS =====
        Route::prefix('borrowings')->group(function () {
            Route::get('/currently-borrowed', [BorrowingController::class, 'currentlyBorrowed']);
            Route::get('/late-returns', [BorrowingController::class, 'lateReturns']);
            Route::get('/unpaid-fines', [BorrowingController::class, 'unpaidFines']);
        });

        // Admin tools dengan rate limiting KHUSUS
        Route::post('/borrowings/auto-check-overdue', [BorrowingController::class, 'autoCheckOverdue'])
            ->middleware('throttle:10,5');

        Route::get('/borrowings/needing-update', [BorrowingController::class, 'getBorrowingsNeedingUpdate']);

        // ===== FINE MANAGEMENT =====
        Route::prefix('fines')->group(function () {
            Route::get('/', [FineController::class, 'index']);
            Route::get('/statistics', [FineController::class, 'statistics']);
        });
    });
});

// ==================== FALLBACK ROUTE ====================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found. Check API documentation.',
        'available_endpoints' => [
            'PUBLIC ENDPOINTS:',
            'GET  /',
            'POST /api/register',
            'POST /api/login',
            'POST /api/forgot-password',
            'POST /api/reset-password',
            'GET  /api/books',
            'GET  /api/categories',

            'AUTHENTICATED ENDPOINTS:',
            'GET  /api/profile',
            'POST /api/logout',
            'POST /api/complete-membership',
            'GET  /api/profile/me',
            'POST /api/profile/update/{id?}',

            'USER ENDPOINTS:',
            'GET  /api/dashboard',
            'GET  /api/check-uncomplete-data',
            'GET  /api/my-fines',
            'GET  /api/books/{id}/download',

            'BORROWING ENDPOINTS:',
            'GET  /api/borrowings/my-borrowings',
            'GET  /api/borrowings/borrowing-history',
            'POST /api/borrowings',
            'GET  /api/borrowings/check-borrow-status',
            'POST /api/borrowings/{id}/extend',
            'POST /api/borrowings/{id}/cancel',
            'GET  /api/borrowings/my-stats',
            'GET  /api/borrowings/{id}/details',
            'GET  /api/borrowings/check-book-status/{bookId}',

            'ADMIN ENDPOINTS:',
            'GET  /api/admin/dashboard/stats',
            'GET  /api/admin/dashboard/chart-data',
            'GET  /api/admin/users',
            'GET  /api/admin/books',
            'GET  /api/admin/borrowings',
            'GET  /api/admin/fines',
        ]
    ], 404);
});
