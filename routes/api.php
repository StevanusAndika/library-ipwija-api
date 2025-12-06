<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\DashboardController;

// Public routes
Route::middleware(['cors'])->group(function () {
    // Auth routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Public book routes
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/books-with-synopsis', [BookController::class, 'getBooksWithSynopsis']);

    // Public category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
});

// Protected routes
Route::middleware(['cors', 'auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update-membership', [AuthController::class, 'updateMembership']);

    // Mahasiswa routes
    Route::middleware(['mahasiswa'])->group(function () {
        // Borrowing routes
        Route::post('/borrowings', [BorrowingController::class, 'store']);
        Route::post('/borrowings/{id}/extend', [BorrowingController::class, 'extend']);
        Route::get('/my-borrowings', [BorrowingController::class, 'myBorrowings']);
        Route::get('/borrowing-history', [BorrowingController::class, 'borrowingHistory']);

        // Fine routes
        Route::get('/my-fines', [FineController::class, 'myFines']);

        // Download ebook
        Route::get('/books/{id}/download', [BookController::class, 'downloadEbook']);
    });

    // Admin routes
    Route::middleware(['admin'])->group(function () {
        // Category management
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Book management
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);

        // Borrowing management
        Route::get('/borrowings', [BorrowingController::class, 'index']);
        Route::post('/borrowings/{id}/approve', [BorrowingController::class, 'approve']);
        Route::post('/borrowings/{id}/reject', [BorrowingController::class, 'reject']);
        Route::post('/borrowings/{id}/mark-borrowed', [BorrowingController::class, 'markAsBorrowed']);
        Route::post('/borrowings/{id}/return', [BorrowingController::class, 'returnBook']);
        Route::get('/currently-borrowed', [BorrowingController::class, 'currentlyBorrowed']);
        Route::get('/late-returns', [BorrowingController::class, 'lateReturns']);

        // Fine management
        Route::get('/fines', [FineController::class, 'index']);
        Route::post('/fines/{id}/mark-paid', [FineController::class, 'markAsPaid']);
        Route::get('/unpaid-fines', [BorrowingController::class, 'unpaidFines']);

        // User management
        Route::get('/users', [DashboardController::class, 'getUsers']);
        Route::put('/users/{id}/toggle-status', [DashboardController::class, 'toggleUserStatus']);
        Route::put('/users/{id}', [DashboardController::class, 'updateUser']);
        // Dashboard stats
        Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    });
});
