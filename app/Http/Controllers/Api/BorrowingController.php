<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\Book;
use App\Models\User;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BorrowingController extends Controller
{
    // ==================== ADMIN METHODS ====================

    /**
     * Get all borrowings (admin)
     */
    public function index(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('nim', 'like', "%{$search}%");
                })->orWhereHas('book', function($bookQuery) use ($search) {
                    $bookQuery->where('title', 'like', "%{$search}%")
                             ->orWhere('author', 'like', "%{$search}%");
                });
            });
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 15);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Borrowings retrieved successfully',
            'data' => $borrowings
        ]);
    }

    /**
     * Get single borrowing (admin)
     */
    public function show($id)
    {
        $borrowing = Borrowing::with(['user', 'book.category', 'fine'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Borrowing retrieved successfully',
            'data' => $borrowing
        ]);
    }

    /**
     * Update borrowing status only (admin) - SIMPLE VERSION
     */
    public function updateStatus($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,borrowed,late,returned,rejected,cancelled',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $borrowing = Borrowing::find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        // Save old status
        $oldStatus = $borrowing->status;

        // Update status
        $borrowing->status = $request->status;

        // Set timestamps based on status
        switch ($request->status) {
            case 'approved':
                $borrowing->approved_at = now();
                break;
            case 'borrowed':
                $borrowing->borrowed_at = now();
                break;
            case 'returned':
                $borrowing->return_date = now();
                $borrowing->returned_at = now();

                // Jika status berubah menjadi returned, cek keterlambatan
                if ($borrowing->due_date && $borrowing->due_date < now()) {
                    $this->createFine($borrowing);
                }
                break;
            case 'rejected':
                $borrowing->rejected_at = now();
                $borrowing->rejection_reason = $request->reason;
                break;
            case 'late':
                $borrowing->status = 'late';
                // Hitung denda otomatis saat status diubah ke late
                $this->createFine($borrowing);
                break;
        }

        // Update notes if provided
        if ($request->has('notes')) {
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['status_change'] = [
                'from' => $oldStatus,
                'to' => $request->status,
                'at' => now()->toDateTimeString(),
                'admin_notes' => $request->notes
            ];
            $borrowing->notes = json_encode($currentNotes);
        }

        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Status peminjaman berhasil diubah',
            'data' => [
                'id' => $borrowing->id,
                'borrow_code' => $borrowing->borrow_code,
                'status_before' => $oldStatus,
                'status_after' => $borrowing->status,
                'user_id' => $borrowing->user_id,
                'book_id' => $borrowing->book_id,
                'timestamps' => [
                    'approved_at' => $borrowing->approved_at ? $borrowing->approved_at->format('Y-m-d H:i:s') : null,
                    'borrowed_at' => $borrowing->borrowed_at ? $borrowing->borrowed_at->format('Y-m-d H:i:s') : null,
                    'return_date' => $borrowing->return_date ? $borrowing->return_date->format('Y-m-d H:i:s') : null,
                ]
            ]
        ]);
    }

    /**
     * Approve borrowing request (admin) - PERBAIKAN VERSION
     */
    public function approve($id)
    {
        $borrowing = Borrowing::with(['book', 'user'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        if ($borrowing->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak dalam status pending'
            ], 400);
        }

        // Check book availability
        $book = $borrowing->book;
        if (!$book || $book->available_stock <= 0 || $book->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak tersedia',
                'book_info' => $book ? [
                    'title' => $book->title,
                    'status' => $book->status,
                    'available_stock' => $book->available_stock,
                    'stock' => $book->stock
                ] : null
            ], 400);
        }

        // Check user can borrow (using canBorrowAnyBook method)
        $user = $borrowing->user;
        if (!$user || !$user->canBorrow()) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak dapat meminjam saat ini',
                'user_info' => $user ? [
                    'name' => $user->name,
                    'status' => $user->status,
                    'can_borrow' => $user->canBorrow(),
                    'active_borrowings' => $user->activeBorrowCount()
                ] : null
            ], 400);
        }

        // PERBAIKAN: Check if user has OTHER unreturned copies of this book
        // Exclude the current borrowing from the check
        $otherUnreturnedBooks = $user->borrowings()
            ->where('book_id', $book->id)
            ->where('id', '!=', $borrowing->id) // Exclude current borrowing
            ->whereIn('status', ['approved', 'borrowed', 'late']) // Only check active statuses, not pending
            ->exists();

        if ($otherUnreturnedBooks) {
            return response()->json([
                'success' => false,
                'message' => 'User masih memiliki peminjaman aktif untuk buku ini',
                'details' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'current_borrowing_id' => $borrowing->id
                ]
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Hanya update status
            $borrowing->status = 'approved';
            $borrowing->approved_at = now();
            $borrowing->save();

            // Decrement book available stock
            $book->available_stock -= 1;
            $book->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Peminjaman berhasil disetujui',
                'data' => $borrowing->load(['user', 'book.category']),
                'book_info' => [
                    'available_stock_before' => $book->available_stock + 1,
                    'available_stock_after' => $book->available_stock
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui peminjaman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject borrowing request (admin)
     */
    public function reject($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $borrowing = Borrowing::find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        if ($borrowing->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing is not pending approval'
            ], 400);
        }

        $borrowing->status = 'rejected';
        $borrowing->rejection_reason = $request->reason;
        $borrowing->rejected_at = now();
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Borrowing rejected successfully',
            'data' => $borrowing
        ]);
    }

    /**
     * Mark as borrowed (admin - when user picks up book)
     */
    public function markAsBorrowed($id)
    {
        $borrowing = Borrowing::with(['book.category'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        if ($borrowing->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman harus disetujui terlebih dahulu'
            ], 400);
        }

        $borrowing->status = 'borrowed';
        $borrowing->borrowed_at = now();

        // Set due date maksimal 7 hari dari sekarang
        $maxDays = 7;
        if ($borrowing->book && $borrowing->book->category) {
            $categoryMaxDays = $borrowing->book->category->max_borrow_days;
            $maxDays = min($categoryMaxDays, 7);
        }

        $borrowing->due_date = now()->addDays($maxDays);
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Buku berhasil ditandai sebagai dipinjam',
            'data' => $borrowing->load(['user', 'book.category']),
            'borrow_details' => [
                'due_date' => $borrowing->due_date->format('d-m-Y'),
                'max_days' => $maxDays,
                'days_remaining' => now()->diffInDays($borrowing->due_date, false)
            ]
        ]);
    }

    /**
     * Return book (admin) - FIXED VERSION WITH FINE GENERATION
     */
    public function returnBook($id)
    {
        $borrowing = Borrowing::with('book')->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        if (!in_array($borrowing->status, ['borrowed', 'late'])) {
            return response()->json([
                'success' => false,
                'message' => 'Buku harus dipinjam terlebih dahulu'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $now = now();
            $borrowing->status = 'returned';
            $borrowing->return_date = $now; // PASTIKAN menggunakan waktu sekarang
            $borrowing->returned_at = $now;

            // Debug logging
            Log::info("Returning book #{$borrowing->id}", [
                'book_id' => $borrowing->book_id,
                'user_id' => $borrowing->user_id,
                'due_date' => $borrowing->due_date ? $borrowing->due_date->format('Y-m-d') : null,
                'return_date' => $borrowing->return_date->format('Y-m-d'),
                'is_late' => $borrowing->due_date ? $borrowing->due_date < $now : false
            ]);

            $borrowing->save();

            // Update book available stock
            if ($borrowing->book) {
                $borrowing->book->available_stock += 1;
                $borrowing->book->save();
            }

            // Check if late and create fine if needed
            if ($borrowing->due_date && $borrowing->due_date < $now) {
                $lateDays = $borrowing->due_date->diffInDays($now, false);
                Log::info("Borrowing #{$borrowing->id} is late. Days: {$lateDays}");
                $this->createFine($borrowing);
            } else {
                Log::info("Borrowing #{$borrowing->id} is not late or has no due date");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Buku berhasil dikembalikan',
                'data' => $borrowing->load(['user', 'book.category', 'fine'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to return book #{$borrowing->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengembalikan buku',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get currently borrowed books (admin)
     */
    public function currentlyBorrowed(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category'])
            ->whereIn('status', ['borrowed', 'late']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('late_only')) {
            $query->where('status', 'late');
        }

        $sortField = $request->get('sort_field', 'due_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $perPage = $request->get('per_page', 15);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Currently borrowed books retrieved successfully',
            'data' => $borrowings
        ]);
    }

    /**
     * Mark borrowing as late (admin - for testing)
     */
    public function markAsLate($id)
    {
        $borrowing = Borrowing::with(['book', 'user'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        // Hanya bisa dari status 'borrowed'
        if ($borrowing->status !== 'borrowed') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya buku yang sedang dipinjam yang bisa diubah ke status late',
                'current_status' => $borrowing->status
            ], 400);
        }

        // Pastikan sudah lewat due date
        if ($borrowing->due_date >= now()) {
            return response()->json([
                'success' => false,
                'message' => 'Buku belum lewat jatuh tempo',
                'due_date' => $borrowing->due_date->format('d-m-Y'),
                'days_remaining' => now()->diffInDays($borrowing->due_date, false)
            ], 400);
        }

        $borrowing->status = 'late';
        $borrowing->save();

        // Generate fine saat ditandai sebagai late
        $this->createFine($borrowing);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah menjadi late',
            'data' => $borrowing->load(['user', 'book.category', 'fine']),
            'late_details' => [
                'due_date' => $borrowing->due_date->format('d-m-Y'),
                'days_overdue' => now()->diffInDays($borrowing->due_date, false) * -1,
                'status_before' => 'borrowed',
                'status_after' => 'late'
            ]
        ]);
    }

    /**
     * Generate fine manually (admin - untuk kasus yang terlewat)
     */
    public function generateFineManually($id)
    {
        $borrowing = Borrowing::with(['book', 'user'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        if ($borrowing->status !== 'returned') {
            return response()->json([
                'success' => false,
                'message' => 'Buku harus sudah dikembalikan'
            ], 400);
        }

        if ($borrowing->fine) {
            return response()->json([
                'success' => false,
                'message' => 'Denda sudah ada untuk peminjaman ini'
            ], 400);
        }

        // Hitung keterlambatan
        $returnDate = $borrowing->return_date ? Carbon::parse($borrowing->return_date) : now();
        $dueDate = Carbon::parse($borrowing->due_date);
        $lateDays = $dueDate->diffInDays($returnDate, false);

        Log::info("Manual fine generation for borrowing #{$borrowing->id}", [
            'due_date' => $dueDate->format('Y-m-d'),
            'return_date' => $returnDate->format('Y-m-d'),
            'late_days' => $lateDays
        ]);

        if ($lateDays <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada keterlambatan. Return date: ' . $returnDate->format('Y-m-d') .
                            ', Due date: ' . $dueDate->format('Y-m-d')
            ], 400);
        }

        $fine = $this->createFine($borrowing);

        return response()->json([
            'success' => true,
            'message' => 'Denda berhasil di-generate',
            'data' => [
                'fine' => $fine,
                'borrowing_id' => $borrowing->id,
                'late_days' => $lateDays,
                'fine_amount' => $fine->amount
            ]
        ]);
    }

    /**
     * Get late returns (admin)
     */
    public function lateReturns(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category'])
            ->where('status', 'late');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sortField = $request->get('sort_field', 'due_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $perPage = $request->get('per_page', 15);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Late returns retrieved successfully',
            'data' => $borrowings
        ]);
    }

    /**
     * Get borrowings with unpaid fines (admin)
     */
    public function unpaidFines(Request $request)
    {
        $query = Fine::with(['user', 'borrowing.book.category'])
            ->where('status', 'unpaid');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sortField = $request->get('sort_field', 'fine_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 15);

        $fines = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Unpaid fines retrieved successfully',
            'data' => $fines
        ]);
    }

    // ==================== USER METHODS ====================

    /**
     * Create borrowing request (user) - ENHANCED VERSION
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:books,id',
            'borrow_date' => 'nullable|date|after_or_equal:today',
            'return_date' => 'nullable|date|after:borrow_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $book = Book::with('category')->find($request->book_id);

        // Cek apakah buku ditemukan
        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak ditemukan'
            ], 404);
        }

        // Enhanced check: Can user borrow this specific book?
        $borrowCheck = $user->canBorrowBook($book->id);

        if (!$borrowCheck['can_borrow']) {
            return response()->json([
                'success' => false,
                'message' => $borrowCheck['message'],
                'details' => $borrowCheck['reasons'],
                'last_borrowing' => isset($borrowCheck['last_borrowing']) && $borrowCheck['last_borrowing'] ? [
                    'id' => $borrowCheck['last_borrowing']->id,
                    'status' => $borrowCheck['last_borrowing']->status,
                    'borrow_date' => $borrowCheck['last_borrowing']->borrow_date ?
                        $borrowCheck['last_borrowing']->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowCheck['last_borrowing']->due_date ?
                        $borrowCheck['last_borrowing']->due_date->format('d-m-Y') : null,
                    'is_overdue' => $borrowCheck['last_borrowing']->isOverdue(),
                ] : null,
                'user_status' => [
                    'is_user' => $user->isUser(),
                    'is_active' => $user->isActive(),
                    'has_unpaid_fines' => $user->hasUnpaidFines(),
                    'total_unpaid_fines' => $user->getTotalUnpaidFines(),
                    'active_borrow_count' => $user->activeBorrowCount(),
                    'max_borrow_limit' => 2,
                    'has_unreturned_book' => $user->hasUnreturnedBook($book->id),
                    'has_borrowed_before' => $user->borrowings()->where('book_id', $book->id)->exists(),
                    'book_borrowing_status' => $user->getBookBorrowingStatus($book->id)
                ]
            ], 400);
        }

        // Check book availability
        if (!$book->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak tersedia',
                'book_status' => [
                    'title' => $book->title,
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock,
                    'status' => $book->status,
                    'status_text' => $book->status == 1 ? 'available' : 'unavailable',
                    'is_available' => $book->isAvailable(),
                    'book_type' => $book->book_type,
                    'category' => $book->category ? [
                        'id' => $book->category->id,
                        'name' => $book->category->name,
                        'can_borrow' => $book->category->can_borrow,
                        'status' => $book->category->status
                    ] : null
                ]
            ], 400);
        }

        // Double check: Check if user has already borrowed this book
        $existingBorrowing = $user->borrowings()
            ->where('book_id', $book->id)
            ->whereIn('status', ['pending', 'approved', 'borrowed', 'late'])
            ->first();

        if ($existingBorrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki peminjaman aktif untuk buku ini',
                'existing_borrowing' => [
                    'id' => $existingBorrowing->id,
                    'borrow_code' => $existingBorrowing->borrow_code,
                    'status' => $existingBorrowing->status,
                    'borrow_date' => $existingBorrowing->borrow_date ?
                        $existingBorrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $existingBorrowing->due_date ?
                        $existingBorrowing->due_date->format('d-m-Y') : null,
                    'created_at' => $existingBorrowing->created_at->format('d-m-Y H:i'),
                    'is_overdue' => $existingBorrowing->isOverdue(),
                    'days_overdue' => $existingBorrowing->isOverdue() ?
                        ($existingBorrowing->due_date ?
                            now()->diffInDays($existingBorrowing->due_date, false) * -1 : 0) : 0
                ]
            ], 400);
        }

        // Check if user has borrowed and returned this book before
        $previousBorrowings = $user->borrowings()
            ->where('book_id', $book->id)
            ->where('status', 'returned')
            ->count();

        // Validasi maksimal 7 hari peminjaman
        $borrowDate = $request->borrow_date ? Carbon::parse($request->borrow_date) : now();
        $returnDate = $request->return_date ? Carbon::parse($request->return_date) : null;

        // Jika ada return_date, validasi maksimal 7 hari
        if ($returnDate) {
            $borrowDays = $borrowDate->diffInDays($returnDate);
            if ($borrowDays > 7) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maksimal periode peminjaman adalah 7 hari',
                    'requested_days' => $borrowDays,
                    'max_days' => 7
                ], 400);
            }

            // Set due_date sesuai dengan return_date yang diminta
            $dueDate = $returnDate;
        } else {
            // Jika tidak ada return_date, set default 7 hari dari borrow_date
            $dueDate = $borrowDate->copy()->addDays(7);
        }

        // Create borrowing
        $borrowing = Borrowing::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'status' => 'pending',
            'notes' => json_encode([
                'previous_borrowings_count' => $previousBorrowings,
                'request_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_borrow_date' => $request->borrow_date,
                'requested_return_date' => $request->return_date
            ])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan peminjaman berhasil diajukan',
            'data' => $borrowing->load(['book.category']),
            'borrow_details' => [
                'borrow_date' => $borrowDate->format('d-m-Y'),
                'due_date' => $dueDate->format('d-m-Y'),
                'days' => $borrowDate->diffInDays($dueDate),
                'max_days_allowed' => 7,
                'previous_borrowings_count' => $previousBorrowings,
                'book_availability' => [
                    'available_stock' => $book->available_stock - 1, // setelah permintaan diajukan
                    'total_stock' => $book->stock
                ]
            ]
        ], 201);
    }

    /**
     * Check borrow status (user - enhanced)
     */
    public function checkBorrowStatus()
    {
        $user = auth()->user();

        $activeBorrowings = $user->activeBorrowings()->with('book')->get()->map(function($borrowing) {
            return [
                'id' => $borrowing->id,
                'book_title' => $borrowing->book->title,
                'book_id' => $borrowing->book_id,
                'status' => $borrowing->status,
                'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                'days_remaining' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) : null,
                'is_overdue' => $borrowing->isOverdue(),
                'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
            ];
        });

        $pendingBorrowings = $user->pendingBorrowings()->with('book')->get()->map(function($borrowing) {
            return [
                'id' => $borrowing->id,
                'book_title' => $borrowing->book->title,
                'book_id' => $borrowing->book_id,
                'status' => $borrowing->status,
                'created_at' => $borrowing->created_at->format('d-m-Y H:i')
            ];
        });

        // Get recently returned books (last 30 days)
        $recentlyReturned = $user->borrowings()
            ->where('status', 'returned')
            ->where('return_date', '>=', now()->subDays(30))
            ->with('book')
            ->orderBy('return_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function($borrowing) {
                return [
                    'book_title' => $borrowing->book->title,
                    'book_id' => $borrowing->book_id,
                    'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                ];
            });

        // Get books with unreturned status
        $unreturnedBooks = $user->borrowings()
            ->whereIn('status', ['borrowed', 'late'])
            ->with('book')
            ->get()
            ->map(function($borrowing) {
                return [
                    'book_id' => $borrowing->book_id,
                    'book_title' => $borrowing->book->title,
                    'status' => $borrowing->status,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'is_overdue' => $borrowing->isOverdue(),
                    'days_overdue' => $borrowing->isOverdue() ?
                        ($borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) * -1 : 0) : 0
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Status Peminjaman',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nim' => $user->nim,
                'role' => $user->role,
                'status' => $user->status,
                'is_active' => $user->isActive(),
                'can_borrow' => $user->canBorrow(),
                'can_borrow_details' => [
                    'is_user' => $user->isUser(),
                    'has_unpaid_fines' => $user->hasUnpaidFines(),
                    'total_unpaid_fines' => 'Rp ' . number_format($user->getTotalUnpaidFines(), 0, ',', '.'),
                    'active_borrow_count' => $user->activeBorrowCount(),
                    'max_borrow_limit' => 2,
                    'pending_approvals' => $user->pendingBorrowings()->count(),
                    'has_late_returns' => $user->hasLateReturns(),
                    'has_unreturned_books' => $unreturnedBooks->count() > 0
                ],
                'active_borrowings' => $activeBorrowings,
                'pending_borrowings' => $pendingBorrowings,
                'unreturned_books' => $unreturnedBooks,
                'recently_returned' => $recentlyReturned,
                'borrowing_stats' => $user->getStats(),
                'warnings' => [
                    'has_overdue_books' => $user->hasLateReturns(),
                    'has_unpaid_fines' => $user->hasUnpaidFines(),
                    'at_borrowing_limit' => $user->activeBorrowCount() >= 2,
                    'has_unreturned_books' => $unreturnedBooks->count() > 0,
                    'total_warnings' => ($user->hasLateReturns() ? 1 : 0) +
                                        ($user->hasUnpaidFines() ? 1 : 0) +
                                        ($user->activeBorrowCount() >= 2 ? 1 : 0) +
                                        ($unreturnedBooks->count() > 0 ? 1 : 0)
                ]
            ]
        ]);
    }

    /**
     * Get user's current borrowings (user)
     */
    public function myBorrowings(Request $request)
    {
        $user = auth()->user();

        $query = $user->borrowings()->with(['book.category'])
            ->whereIn('status', ['pending', 'approved', 'borrowed', 'late']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 10);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data peminjaman Anda',
            'data' => $borrowings
        ]);
    }

    /**
     * Auto-check and update all overdue borrowings (admin)
     */
    public function autoCheckOverdue(Request $request)
    {
        $results = Borrowing::bulkUpdateOverdue();

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Auto-check for overdue borrowings completed'
        ], $results));
    }

    /**
     * Get borrowings that need auto-update (admin)
     */
    public function getBorrowingsNeedingUpdate(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category'])
            ->where('status', 'borrowed')
            ->where('due_date', '<', now());

        $perPage = $request->get('per_page', 20);
        $borrowings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Borrowings that need auto-update to late status',
            'data' => $borrowings,
            'count' => $borrowings->total()
        ]);
    }

    /**
     * Get user's borrowing history (user)
     */
    public function borrowingHistory(Request $request)
    {
        $user = auth()->user();

        $query = $user->borrowings()->with(['book.category'])
            ->where('status', 'returned');

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('book', function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort_field', 'return_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 10);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat peminjaman',
            'data' => $borrowings
        ]);
    }

    /**
     * Check if user can borrow specific book
     */
    public function checkBookBorrowStatus($bookId)
    {
        $user = auth()->user();
        $book = Book::with('category')->find($bookId);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak ditemukan'
            ], 404);
        }

        $strictCheck = $user->canBorrowAnyBook();
        $bookCheck = $user->canBorrowBook($book->id);

        $userHistory = $user->borrowings()
            ->where('book_id', $book->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                    'is_overdue' => $borrowing->isOverdue(),
                    'created_at' => $borrowing->created_at->format('d-m-Y H:i'),
                ];
            });

        $bookBorrowingStatus = $user->getBookBorrowingStatus($book->id);

        return response()->json([
            'success' => true,
            'message' => 'Status peminjaman buku',
            'data' => [
                'book' => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author,
                    'isbn' => $book->isbn,
                    'is_available' => $book->isAvailable(),
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock,
                ],
                'user_can_borrow_any_book' => $strictCheck['can_borrow'],
                'user_can_borrow_this_book' => $bookCheck['can_borrow'],
                'borrow_check_details' => [
                    'strict_check' => $strictCheck,
                    'book_specific_check' => $bookCheck,
                    'has_unreturned_copy' => $bookBorrowingStatus['has_unreturned_copy'] ?? false,
                    'has_borrowed_before' => $bookBorrowingStatus['has_borrowed_before'] ?? false,
                ],
                'current_borrowing' => $bookBorrowingStatus['current_borrowing'] ?? null,
                'borrowing_history' => $userHistory,
                'statistics' => [
                    'total_times_borrowed' => $bookBorrowingStatus['total_times_borrowed'] ?? 0,
                    'successful_returns' => $bookBorrowingStatus['successful_returns'] ?? 0,
                ],
                'book_availability' => [
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock,
                    'is_available' => $book->isAvailable(),
                    'status' => $book->status_text,
                ]
            ]
        ]);
    }

    /**
     * Extend borrowing period (user)
     */
    public function extend($id)
    {
        $borrowing = Borrowing::with(['book.category'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        $user = auth()->user();
        if ($borrowing->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        if ($borrowing->status !== 'borrowed') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya buku yang sedang dipinjam yang dapat diperpanjang'
            ], 400);
        }

        // Check if already extended
        if ($borrowing->is_extended) {
            return response()->json([
                'success' => false,
                'message' => 'Periode peminjaman sudah diperpanjang sebelumnya'
            ], 400);
        }

        // Check if within extension window (max 3 days before due date)
        $daysBeforeDue = $borrowing->due_date->diffInDays(now(), false);
        if ($daysBeforeDue > -3) {
            return response()->json([
                'success' => false,
                'message' => 'Perpanjangan hanya dapat diajukan maksimal 3 hari sebelum tanggal jatuh tempo',
                'days_before_due' => $daysBeforeDue,
                'required_days_before_due' => '3 atau lebih'
            ], 400);
        }

        // Extend by 3 days
        $extensionDays = 3;

        // Cek apakah extension melebihi maksimal 7 hari total
        $newDueDate = $borrowing->due_date->copy()->addDays($extensionDays);
        $totalBorrowDays = $borrowing->borrow_date->diffInDays($newDueDate);

        if ($totalBorrowDays > 7) {
            // Hitung extension yang diperbolehkan
            $maxTotalDays = 7;
            $alreadyBorrowedDays = $borrowing->borrow_date->diffInDays(now());
            $allowedExtension = $maxTotalDays - $alreadyBorrowedDays;

            if ($allowedExtension <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat memperpanjang. Maksimal periode peminjaman 7 hari sudah tercapai.'
                ], 400);
            }

            $extensionDays = $allowedExtension;
            $newDueDate = $borrowing->due_date->copy()->addDays($extensionDays);
        }

        $borrowing->due_date = $newDueDate;
        $borrowing->is_extended = true;
        $borrowing->extended_due_date = $newDueDate;
        $borrowing->extended_at = now();
        $borrowing->notes = json_encode(array_merge(
            json_decode($borrowing->notes, true) ?? [],
            [
                'extended_at' => now()->toDateTimeString(),
                'extension_days' => $extensionDays,
                'original_due_date' => $borrowing->due_date->format('Y-m-d'),
                'new_due_date' => $newDueDate->format('Y-m-d')
            ]
        ));
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Periode peminjaman berhasil diperpanjang',
            'data' => $borrowing,
            'extension_details' => [
                'original_due_date' => $borrowing->due_date->format('d-m-Y'),
                'new_due_date' => $newDueDate->format('d-m-Y'),
                'extension_days' => $extensionDays,
                'total_borrow_days' => $totalBorrowDays,
                'max_total_days' => 7
            ]
        ]);
    }

    /**
     * Cancel borrowing request (user)
     */
    public function cancel($id)
    {
        $borrowing = Borrowing::find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        $user = auth()->user();
        if ($borrowing->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        if ($borrowing->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan pending yang dapat dibatalkan'
            ], 400);
        }

        $borrowing->status = 'cancelled';
        $borrowing->save();

        // Increment book stock jika dibatalkan
        if ($borrowing->book) {
            $borrowing->book->available_stock += 1;
            $borrowing->book->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan peminjaman berhasil dibatalkan',
            'data' => $borrowing
        ]);
    }

    /**
     * Get borrowing statistics (user)
     */
    public function myStats()
    {
        $user = auth()->user();

        $stats = [
            'total_borrowings' => $user->borrowings()->count(),
            'currently_borrowed' => $user->activeBorrowCount(),
            'pending_requests' => $user->pendingBorrowings()->count(),
            'returned' => $user->borrowings()->where('status', 'returned')->count(),
            'late_returns' => $user->borrowings()->where('status', 'late')->count(),
            'fines' => $user->fines()->count(),
            'unpaid_fines' => $user->unpaidFines()->count(),
            'total_unpaid_fine_amount' => $user->getTotalUnpaidFines(),
            'books_with_unreturned_copies' => $user->borrowings()
                ->whereIn('status', ['borrowed', 'late'])
                ->distinct('book_id')
                ->count('book_id'),
            'recent_activity' => $user->getRecentActivity(5)
        ];

        return response()->json([
            'success' => true,
            'message' => 'Statistik peminjaman',
            'data' => $stats
        ]);
    }

    /**
     * Get borrowing details for notification/email
     */
    public function getBorrowingDetails($id)
    {
        $borrowing = Borrowing::with(['user', 'book', 'book.category'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        $user = auth()->user();
        if ($borrowing->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $details = [
            'borrowing' => [
                'id' => $borrowing->id,
                'borrow_code' => $borrowing->borrow_code,
                'status' => $borrowing->status,
                'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                'is_extended' => $borrowing->is_extended,
                'fine_amount' => $borrowing->fine_amount,
                'fine_paid' => $borrowing->fine_paid,
                'is_overdue' => $borrowing->isOverdue(),
                'days_overdue' => $borrowing->isOverdue() ?
                    ($borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) * -1 : 0) : 0
            ],
            'user' => [
                'name' => $borrowing->user->name,
                'nim' => $borrowing->user->nim,
                'email' => $borrowing->user->email,
            ],
            'book' => [
                'title' => $borrowing->book->title,
                'author' => $borrowing->book->author,
                'isbn' => $borrowing->book->isbn,
                'category' => $borrowing->book->category ? $borrowing->book->category->name : null,
            ],
            'fine' => $borrowing->fine ? [
                'amount' => $borrowing->fine->amount,
                'late_days' => $borrowing->fine->late_days,
                'status' => $borrowing->fine->status,
                'fine_date' => $borrowing->fine->fine_date ? $borrowing->fine->fine_date->format('d-m-Y') : null,
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail peminjaman',
            'data' => $details
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create fine for late return - FIXED VERSION
     */
    private function createFine(Borrowing $borrowing)
    {
        try {
            // Gunakan return_date aktual jika sudah ada, jika belum gunakan waktu sekarang
            $returnDate = $borrowing->return_date ? Carbon::parse($borrowing->return_date) : now();
            $dueDate = Carbon::parse($borrowing->due_date);

            // Calculate late days (positive jika terlambat)
            $lateDays = $returnDate->diffInDays($dueDate, false) * -1;

            Log::info("Creating fine for borrowing #{$borrowing->id}", [
                'due_date' => $dueDate->format('Y-m-d'),
                'return_date' => $returnDate->format('Y-m-d'),
                'calculated_late_days' => $lateDays
            ]);

            // Jika tidak terlambat, return null
            if ($lateDays <= 0) {
                Log::info("No fine needed for borrowing #{$borrowing->id}. Late days: {$lateDays}");
                return null;
            }

            // Fine calculation: Rp 1,000 per day
            $finePerDay = 1000;
            $amount = $lateDays * $finePerDay;

            // Cek apakah fine sudah ada
            if ($borrowing->fine) {
                Log::info("Fine already exists for borrowing #{$borrowing->id}. Updating...");
                $fine = $borrowing->fine;
                $fine->amount = $amount;
                $fine->late_days = $lateDays;
                $fine->save();
            } else {
                // Create new fine
                $fine = Fine::create([
                    'borrowing_id' => $borrowing->id,
                    'user_id' => $borrowing->user_id,
                    'amount' => $amount,
                    'late_days' => $lateDays,
                    'fine_date' => now(),
                    'status' => 'unpaid',
                    'description' => 'Denda keterlambatan pengembalian buku: ' . ($borrowing->book ? $borrowing->book->title : 'Unknown Book')
                ]);
            }

            // Update borrowing with fine info
            $borrowing->fine_amount = $amount;
            $borrowing->late_days = $lateDays;
            $borrowing->fine_paid = false;
            $borrowing->save();

            Log::info("Fine created/updated for borrowing #{$borrowing->id}: {$lateDays} days, Rp {$amount}");

            return $fine;
        } catch (\Exception $e) {
            Log::error("Error creating fine for borrowing #{$borrowing->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update late borrowings (should be run via cron job)
     */
    public function updateLateBorrowings()
    {
        $now = now();
        $lateBorrowings = Borrowing::where('status', 'borrowed')
            ->where('due_date', '<', $now)
            ->get();

        foreach ($lateBorrowings as $borrowing) {
            $borrowing->status = 'late';
            $borrowing->save();

            // Generate fine saat status diubah ke late
            $this->createFine($borrowing);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peminjaman terlambat diperbarui',
            'count' => $lateBorrowings->count()
        ]);
    }
}
