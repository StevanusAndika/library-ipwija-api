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
    // ==================== PUBLIC METHODS ====================

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
     * Create borrowing request (user)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:books,id',
            'notes' => 'nullable|string|max:500',
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

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak ditemukan'
            ], 404);
        }

        // Cek apakah user bisa meminjam buku ini
        $borrowCheck = $user->canBorrowBook($book->id);

        if (!$borrowCheck['can_borrow']) {
            return response()->json([
                'success' => false,
                'message' => $borrowCheck['message'],
                'details' => $borrowCheck['reasons']
            ], 400);
        }

        // Cek ketersediaan buku - PERUBAHAN DI SINI
        // Saat pending, kita hanya mengecek apakah ada stok yang tersedia
        if ($book->available_stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak tersedia untuk dipinjam',
                'book_status' => [
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock,
                    'status' => $book->status
                ]
            ], 400);
        }

        // Double check: user sudah meminjam buku yang sama yang belum dikembalikan
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
                    'status' => $existingBorrowing->status,
                    'borrow_date' => $existingBorrowing->borrow_date ?
                        $existingBorrowing->borrow_date->format('d-m-Y') : null
                ]
            ], 400);
        }

        // Hitung tanggal
        $borrowDate = $request->borrow_date ? Carbon::parse($request->borrow_date) : now();
        $returnDate = $request->return_date ? Carbon::parse($request->return_date) : null;

        // Set due date maksimal 7 hari
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
            $dueDate = $returnDate;
        } else {
            $dueDate = $borrowDate->copy()->addDays(7);
        }

        // Generate unique borrow code
        $borrowCode = 'BOR-' . strtoupper(uniqid());

        // Simpan notes dari request
        $notes = $request->notes ?: '';

        DB::beginTransaction();
        try {
            $borrowing = Borrowing::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'borrow_code' => $borrowCode,
                'borrow_date' => $borrowDate,
                'due_date' => $dueDate,
                'status' => 'pending',
                'notes' => json_encode([
                    'user_notes' => $notes,
                    'previous_borrowings_count' => $user->borrowings()->where('book_id', $book->id)->count(),
                  //
                    'stock_operation' => 'no_stock_reduction_on_pending'
                ])
            ]);

            // PERUBAHAN PENTING: TIDAK mengurangi stok buku saat status pending
            // Stok hanya akan dikurangi saat status diubah menjadi 'approved'
            // $book->available_stock -= 1;  // DIHAPUS
            // $book->save();  // DIHAPUS

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan peminjaman berhasil diajukan. Menunggu persetujuan admin.',
                'data' => $borrowing->load(['book.category']),
                'borrow_details' => [
                    'borrow_code' => $borrowCode,
                    'status' => 'pending',
                    'borrow_date' => $borrowDate->format('d-m-Y'),
                    'due_date' => $dueDate->format('d-m-Y'),
                    'days' => $borrowDate->diffInDays($dueDate),
                    'max_days_allowed' => 7,
                    'note' => 'Stok buku belum dikurangi karena status masih pending.'
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan permintaan peminjaman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== ADMIN METHODS ====================

    /**
     * Approve borrowing request (admin)
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

        // Cek ketersediaan buku
        $book = $borrowing->book;
        if (!$book || $book->available_stock <= 0 || $book->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak tersedia untuk disetujui',
                'book_info' => $book ? [
                    'title' => $book->title,
                    'status' => $book->status,
                    'available_stock' => $book->available_stock,
                    'stock' => $book->stock
                ] : null
            ], 400);
        }

        // Cek user bisa meminjam
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

        // Cek jika user punya peminjaman aktif untuk buku yang sama
        $otherUnreturnedBooks = $user->borrowings()
            ->where('book_id', $book->id)
            ->where('id', '!=', $borrowing->id)
            ->whereIn('status', ['approved', 'borrowed', 'late'])
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
            // PERUBAHAN PENTING: Kurangi stok buku saat status diubah menjadi approved
            $book->available_stock -= 1;
            $book->save();

            // Update status dan catatan
            $borrowing->status = 'approved';
            $borrowing->approved_at = now();

            // Update notes untuk mencatat pengurangan stok
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['approval_details'] = [
                'approved_at' => now()->toDateTimeString(),
                'stock_reduced' => true,
                'previous_available_stock' => $book->available_stock + 1,
                'new_available_stock' => $book->available_stock,
                'admin_action' => 'stock_reduced_on_approval'
            ];
            $borrowing->notes = json_encode($currentNotes);

            $borrowing->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Peminjaman berhasil disetujui dan stok buku telah dikurangi',
                'data' => $borrowing->load(['user', 'book.category']),
                'stock_info' => [
                    'book_title' => $book->title,
                    'previous_stock' => $book->available_stock + 1,
                    'current_stock' => $book->available_stock,
                    'total_stock' => $book->stock
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
            'reason' => 'required|string|max:500',
            'admin_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $borrowing = Borrowing::with(['book'])->find($id);

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

        DB::beginTransaction();
        try {
            $borrowing->status = 'rejected';
            $borrowing->rejection_reason = $request->reason;
            $borrowing->rejected_at = now();

            // Update notes dengan admin notes
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['admin_rejection'] = [
                'reason' => $request->reason,
                'admin_notes' => $request->admin_notes,
                'rejected_at' => now()->toDateTimeString(),
                'note' => 'Stok tidak berubah karena belum dikurangi sejak awal (status pending)'
            ];
            $borrowing->notes = json_encode($currentNotes);
            $borrowing->save();

            // PERUBAHAN: TIDAK perlu menambah stok buku karena stok belum pernah dikurangi
            // Hanya log saja bahwa stok tidak berubah
            if ($borrowing->book) {
                Log::info("Borrowing #{$borrowing->id} rejected. Stock unchanged at {$borrowing->book->available_stock} because it was never reduced (pending status).");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan peminjaman ditolak. Stok buku tidak berubah.',
                'data' => $borrowing->load(['user', 'book.category'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject borrowing',
                'error' => $e->getMessage()
            ], 500);
        }
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

        // Periksa apakah stok masih tersedia
        $book = $borrowing->book;
        if (!$book || $book->available_stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stok buku tidak tersedia untuk dipinjam',
                'book_info' => $book ? [
                    'title' => $book->title,
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock
                ] : null
            ], 400);
        }

        DB::beginTransaction();
        try {
            $borrowing->status = 'borrowed';
            $borrowing->borrowed_at = now();

            // Set due date maksimal 7 hari dari sekarang
            $maxDays = 7;
            if ($borrowing->book && $borrowing->book->category) {
                $categoryMaxDays = $borrowing->book->category->max_borrow_days;
                $maxDays = min($categoryMaxDays, 7);
            }

            $borrowing->due_date = now()->addDays($maxDays);

            // Update catatan
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['borrowed_details'] = [
                'borrowed_at' => now()->toDateTimeString(),
                'due_date_set_to' => $borrowing->due_date->format('Y-m-d'),
                'note' => 'Stok sudah dikurangi saat approval'
            ];
            $borrowing->notes = json_encode($currentNotes);

            $borrowing->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Buku berhasil ditandai sebagai dipinjam',
                'data' => $borrowing->load(['user', 'book.category']),
                'borrow_details' => [
                    'due_date' => $borrowing->due_date->format('d-m-Y'),
                    'max_days' => $maxDays,
                    'days_remaining' => now()->diffInDays($borrowing->due_date, false),
                    'stock_status' => 'Stok sudah dikurangi saat persetujuan (approved)'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai buku sebagai dipinjam',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return book (admin)
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
            $borrowing->return_date = $now;
            $borrowing->returned_at = $now;

            // Update catatan
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['return_details'] = [
                'returned_at' => $now->toDateTimeString(),
                'stock_increased' => true
            ];
            $borrowing->notes = json_encode($currentNotes);

            $borrowing->save();

            // Update book available stock - TAMBAHKAN stok saat buku dikembalikan
            if ($borrowing->book) {
                $borrowing->book->available_stock += 1;
                $borrowing->book->save();
            }

            // Check if late and create fine if needed
            if ($borrowing->due_date && $borrowing->due_date < $now) {
                $this->createFine($borrowing);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Buku berhasil dikembalikan dan stok ditambahkan kembali',
                'data' => $borrowing->load(['user', 'book.category', 'fine']),
                'stock_info' => $borrowing->book ? [
                    'book_title' => $borrowing->book->title,
                    'previous_stock' => $borrowing->book->available_stock - 1,
                    'current_stock' => $borrowing->book->available_stock
                ] : null
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

        if ($borrowing->status !== 'borrowed') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya buku yang sedang dipinjam yang bisa diubah ke status late'
            ], 400);
        }

        // Pastikan sudah lewat due date
        if ($borrowing->due_date >= now()) {
            return response()->json([
                'success' => false,
                'message' => 'Buku belum lewat jatuh tempo'
            ], 400);
        }

        $borrowing->status = 'late';

        // Update catatan
        $currentNotes = json_decode($borrowing->notes, true) ?? [];
        $currentNotes['marked_late_at'] = now()->toDateTimeString();
        $borrowing->notes = json_encode($currentNotes);

        $borrowing->save();

        // Generate fine saat ditandai sebagai late
        $this->createFine($borrowing);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah menjadi late',
            'data' => $borrowing->load(['user', 'book.category', 'fine'])
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
            'admin_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $borrowing = Borrowing::with('book')->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Peminjaman tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Save old status
            $oldStatus = $borrowing->status;

            // Handle stock operations based on status change
            $stockOperation = null;

            if ($oldStatus === 'pending' && $request->status === 'approved') {
                // Kurangi stok saat mengubah dari pending ke approved
                if ($borrowing->book && $borrowing->book->available_stock > 0) {
                    $borrowing->book->available_stock -= 1;
                    $borrowing->book->save();
                    $stockOperation = 'stock_reduced';
                }
            } elseif ($oldStatus === 'approved' && $request->status === 'rejected') {
                // Kembalikan stok jika mengubah dari approved ke rejected
                if ($borrowing->book) {
                    $borrowing->book->available_stock += 1;
                    $borrowing->book->save();
                    $stockOperation = 'stock_restored';
                }
            } elseif (in_array($oldStatus, ['borrowed', 'late']) && $request->status === 'returned') {
                // Tambah stok saat buku dikembalikan
                if ($borrowing->book) {
                    $borrowing->book->available_stock += 1;
                    $borrowing->book->save();
                    $stockOperation = 'stock_restored_on_return';
                }
            } elseif ($oldStatus === 'pending' && $request->status === 'rejected') {
                // Stok tidak berubah karena belum pernah dikurangi
                $stockOperation = 'no_stock_change_pending_to_rejected';
            }

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
                    $this->createFine($borrowing);
                    break;
            }

            // Update notes dengan admin notes dan info stok
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['status_change'] = [
                'from' => $oldStatus,
                'to' => $request->status,
                'at' => now()->toDateTimeString(),
                'admin_notes' => $request->admin_notes,
                'stock_operation' => $stockOperation,
                'book_available_stock' => $borrowing->book ? $borrowing->book->available_stock : null
            ];
            $borrowing->notes = json_encode($currentNotes);

            $borrowing->save();

            DB::commit();

            $message = 'Status peminjaman berhasil diubah';
            if ($stockOperation === 'stock_reduced') {
                $message .= ' (stok buku dikurangi)';
            } elseif ($stockOperation === 'stock_restored') {
                $message .= ' (stok buku dikembalikan)';
            } elseif ($stockOperation === 'stock_restored_on_return') {
                $message .= ' (stok buku ditambahkan kembali)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $borrowing->load(['user', 'book.category']),
                'stock_operation' => $stockOperation
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status peminjaman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel borrowing request (user)
     */
    public function cancel($id)
    {
        $borrowing = Borrowing::with('book')->find($id);

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

        DB::beginTransaction();
        try {
            $borrowing->status = 'cancelled';

            // Update catatan
            $currentNotes = json_decode($borrowing->notes, true) ?? [];
            $currentNotes['cancelled_at'] = now()->toDateTimeString();
            $currentNotes['cancellation_note'] = 'Dibatalkan oleh user. Stok tidak berubah karena status masih pending.';
            $borrowing->notes = json_encode($currentNotes);

            $borrowing->save();

            // PERUBAHAN: TIDAK perlu menambah stok karena stok belum pernah dikurangi
            // Hanya log informasinya
            if ($borrowing->book) {
                Log::info("Borrowing #{$borrowing->id} cancelled by user. Stock unchanged: {$borrowing->book->available_stock}");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan peminjaman berhasil dibatalkan. Stok buku tidak berubah.',
                'data' => $borrowing
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan permintaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create fine for late return
     */
    private function createFine(Borrowing $borrowing)
    {
        try {
            $returnDate = $borrowing->return_date ? Carbon::parse($borrowing->return_date) : now();
            $dueDate = Carbon::parse($borrowing->due_date);
            $lateDays = $returnDate->diffInDays($dueDate, false) * -1;

            if ($lateDays <= 0) {
                return null;
            }

            // Fine calculation: Rp 1,000 per day
            $finePerDay = 1000;
            $amount = $lateDays * $finePerDay;

            if ($borrowing->fine) {
                $fine = $borrowing->fine;
                $fine->amount = $amount;
                $fine->late_days = $lateDays;
                $fine->save();
            } else {
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

            $borrowing->fine_amount = $amount;
            $borrowing->late_days = $lateDays;
            $borrowing->fine_paid = false;
            $borrowing->save();

            return $fine;
        } catch (\Exception $e) {
            Log::error("Error creating fine for borrowing #{$borrowing->id}: " . $e->getMessage());
            return null;
        }
    }

    // ==================== METODE-METODE LAIN TETAP SAMA ====================
    // Berikut adalah method-method lain yang tidak berubah logika stoknya

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
        $query = Borrowing::with(['user', 'book.category', 'fine'])
            ->whereHas('fine', function($q) {
                $q->where('status', 'unpaid');
            });

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sortField = $request->get('sort_field', 'due_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 15);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Unpaid fines retrieved successfully',
            'data' => $borrowings
        ]);
    }

    /**
     * Generate fine manually (admin)
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

        if ($lateDays <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada keterlambatan'
            ], 400);
        }

        $fine = $this->createFine($borrowing);

        return response()->json([
            'success' => true,
            'message' => 'Denda berhasil di-generate',
            'data' => [
                'fine' => $fine,
                'borrowing_id' => $borrowing->id,
                'late_days' => $lateDays
            ]
        ]);
    }

    /**
     * Auto-check and update all overdue borrowings (admin)
     */
    public function autoCheckOverdue(Request $request)
    {
        $now = now();
        $overdueBorrowings = Borrowing::where('status', 'borrowed')
            ->where('due_date', '<', $now)
            ->get();

        $updatedCount = 0;
        foreach ($overdueBorrowings as $borrowing) {
            $borrowing->status = 'late';
            $borrowing->save();

            $this->createFine($borrowing);
            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Auto-check for overdue borrowings completed',
            'data' => [
                'checked_at' => $now->format('Y-m-d H:i:s'),
                'overdue_borrowings_found' => $overdueBorrowings->count(),
                'updated_to_late' => $updatedCount
            ]
        ]);
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

    // ==================== USER METHODS ====================

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
     * Check borrow status (user)
     */
    public function checkBorrowStatus()
    {
        $user = auth()->user();

        $activeBorrowings = $user->borrowings()
            ->whereIn('status', ['pending', 'approved', 'borrowed', 'late'])
            ->with('book')
            ->get()
            ->map(function($borrowing) {
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

        return response()->json([
            'success' => true,
            'message' => 'Status Peminjaman',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'can_borrow' => $user->canBorrow(),
                'active_borrowings' => $activeBorrowings,
                'active_borrow_count' => $user->activeBorrowCount(),
                'pending_approvals' => $user->borrowings()->where('status', 'pending')->count(),
            ]
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

        $borrowCheck = $user->canBorrowBook($book->id);

        return response()->json([
            'success' => true,
            'message' => 'Status peminjaman buku',
            'data' => [
                'book' => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'is_available' => $book->isAvailable(),
                    'available_stock' => $book->available_stock,
                ],
                'user_can_borrow_this_book' => $borrowCheck['can_borrow'],
                'details' => $borrowCheck
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
                'message' => 'Perpanjangan hanya dapat diajukan maksimal 3 hari sebelum tanggal jatuh tempo'
            ], 400);
        }

        // Extend by 3 days
        $extensionDays = 3;
        $newDueDate = $borrowing->due_date->copy()->addDays($extensionDays);
        $totalBorrowDays = $borrowing->borrow_date->diffInDays($newDueDate);

        // Cek maksimal 7 hari
        if ($totalBorrowDays > 7) {
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
                'extension_days' => $extensionDays
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
                'total_borrow_days' => $totalBorrowDays
            ]
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
            'pending_requests' => $user->borrowings()->where('status', 'pending')->count(),
            'returned' => $user->borrowings()->where('status', 'returned')->count(),
            'late_returns' => $user->borrowings()->where('status', 'late')->count(),
            'unpaid_fines' => $user->fines()->where('status', 'unpaid')->count(),
            'total_unpaid_fine_amount' => $user->getTotalUnpaidFines()
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
                'is_overdue' => $borrowing->isOverdue()
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
                'status' => $borrowing->fine->status
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail peminjaman',
            'data' => $details
        ]);
    }

    /**
     * Mark fine as paid (admin)
     */
    public function markFinePaid($id)
    {
        $borrowing = Borrowing::with(['book', 'user', 'fine'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        if (!$borrowing->fine) {
            return response()->json([
                'success' => false,
                'message' => 'No fine found for this borrowing'
            ], 400);
        }

        $borrowing->fine->status = 'paid';
        $borrowing->fine->paid_at = now();
        $borrowing->fine->save();

        $borrowing->fine_paid = true;
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Fine marked as paid',
            'data' => $borrowing->load(['user', 'book.category', 'fine'])
        ]);
    }
}
