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
     * Approve borrowing request (admin)
     */
    public function approve($id)
    {
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

        // Check book availability
        $book = $borrowing->book;
        if ($book->available_stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Book is not available'
            ], 400);
        }

        // Check user can borrow
        $user = $borrowing->user;
        if (!$user->canBorrow()) {
            return response()->json([
                'success' => false,
                'message' => 'User cannot borrow at this time'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Update borrowing status
            $borrowing->status = 'approved';
            $borrowing->approved_at = now();
            $borrowing->save();

            // Update book available stock
            $book->available_stock -= 1;
            $book->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Borrowing approved successfully',
                'data' => $borrowing->load(['user', 'book.category'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve borrowing',
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
        $borrowing = Borrowing::find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        if ($borrowing->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing must be approved first'
            ], 400);
        }

        $borrowing->status = 'borrowed';
        $borrowing->borrowed_at = now();

        // Set due date maksimal 7 hari dari sekarang
        // Gunakan yang lebih kecil: category max_borrow_days atau 7 hari
        $categoryMaxDays = $borrowing->book->category->max_borrow_days;
        $maxDays = min($categoryMaxDays, 7); // Maksimal 7 hari

        $borrowing->due_date = now()->addDays($maxDays);
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Book marked as borrowed',
            'data' => $borrowing->load(['user', 'book.category']),
            'borrow_details' => [
                'borrowed_at' => $borrowing->borrowed_at,
                'due_date' => $borrowing->due_date,
                'max_days' => $maxDays,
                'days_remaining' => now()->diffInDays($borrowing->due_date, false)
            ]
        ]);
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
                'message' => 'Borrowing not found'
            ], 404);
        }

        if (!in_array($borrowing->status, ['borrowed', 'late'])) {
            return response()->json([
                'success' => false,
                'message' => 'Book must be borrowed first'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $now = now();
            $borrowing->status = 'returned';
            $borrowing->returned_at = $now;
            $borrowing->save();

            // Update book available stock
            $book = $borrowing->book;
            $book->available_stock += 1;
            $book->save();

            // Check if late and create fine if needed
            if ($borrowing->due_date < $now) {
                $this->createFine($borrowing);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book returned successfully',
                'data' => $borrowing->load(['user', 'book.category', 'fine'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to return book',
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

        $sortField = $request->get('sort_field', 'returned_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 15);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Unpaid fines retrieved successfully',
            'data' => $borrowings
        ]);
    }

    // ==================== MAHASISWA METHODS ====================

    /**
     * Create borrowing request (mahasiswa)
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
        $book = Book::find($request->book_id);

        // Debug: Check user status
        $userStatus = [
            'is_mahasiswa' => $user->isMahasiswa(),
            'is_anggota' => $user->is_anggota,
            'is_active' => $user->is_active,
            'has_unpaid_fines' => $user->hasUnpaidFines(),
            'has_late_returns' => $user->hasLateReturns(),
            'active_borrow_count' => $user->activeBorrowCount(),
            'max_borrow_limit' => 2
        ];

        // Check user can borrow
        if (!$user->canBorrow()) {
            // Berikan detail error yang lebih spesifik
            $errorDetails = [];

            if (!$user->is_anggota) {
                $errorDetails[] = 'User is not a member. Please complete membership registration.';
            }

            if (!$user->is_active) {
                $errorDetails[] = 'User account is not active. Please contact administrator.';
            }

            if ($user->hasUnpaidFines()) {
                $errorDetails[] = 'User has unpaid fines. Please pay your fines first.';
            }

            if ($user->hasLateReturns()) {
                $errorDetails[] = 'User has late returns. Please return overdue books first.';
            }

            if ($user->activeBorrowCount() >= 2) {
                $errorDetails[] = 'User has reached maximum active borrowings (2). Please return some books first.';
            }

            return response()->json([
                'success' => false,
                'message' => 'You cannot borrow at this time. Check your borrowing status.',
                'details' => $errorDetails,
                'user_status' => $userStatus
            ], 400);
        }

        // Check book availability
        if (!$book->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Book is not available',
                'book_status' => [
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock,
                    'status' => $book->status,
                    'is_available' => $book->isAvailable()
                ]
            ], 400);
        }

        // Check if user has already borrowed this book
        $existingBorrowing = Borrowing::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->whereIn('status', ['pending', 'approved', 'borrowed', 'late'])
            ->first();

        if ($existingBorrowing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active request or borrowing for this book',
                'existing_borrowing' => [
                    'id' => $existingBorrowing->id,
                    'status' => $existingBorrowing->status,
                    'created_at' => $existingBorrowing->created_at
                ]
            ], 400);
        }

        // Validasi maksimal 7 hari peminjaman
        $borrowDate = $request->borrow_date ? Carbon::parse($request->borrow_date) : now();
        $returnDate = $request->return_date ? Carbon::parse($request->return_date) : null;

        // Jika ada return_date, validasi maksimal 7 hari
        if ($returnDate) {
            $borrowDays = $borrowDate->diffInDays($returnDate);
            if ($borrowDays > 7) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum borrowing period is 7 days',
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Borrowing request submitted successfully',
            'data' => $borrowing->load(['book.category']),
            'borrow_details' => [
                'borrow_date' => $borrowDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'days' => $borrowDate->diffInDays($dueDate),
                'max_days_allowed' => 7
            ]
        ], 201);
    }

    /**
     * Check borrow status (mahasiswa - debugging)
     */
    public function checkBorrowStatus()
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'message' => 'Borrow status check',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nim' => $user->nim,
                'role' => $user->role,
                'is_anggota' => $user->is_anggota,
                'is_active' => $user->is_active,
                'can_borrow' => $user->canBorrow(),
                'can_borrow_details' => [
                    'is_mahasiswa' => $user->isMahasiswa(),
                    'has_unpaid_fines' => $user->hasUnpaidFines(),
                    'total_unpaid_fines' => $user->getTotalUnpaidFines(),
                    'has_late_returns' => $user->hasLateReturns(),
                    'active_borrow_count' => $user->activeBorrowCount(),
                    'max_borrow_limit' => 2,
                    'pending_approvals' => $user->pendingBorrowings()->count()
                ],
                'active_borrowings' => $user->activeBorrowings()->with('book')->get()->map(function($borrowing) {
                    return [
                        'id' => $borrowing->id,
                        'book_title' => $borrowing->book->title,
                        'status' => $borrowing->status,
                        'due_date' => $borrowing->due_date,
                        'days_remaining' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) : null
                    ];
                }),
                'pending_borrowings' => $user->pendingBorrowings()->count()
            ]
        ]);
    }

    /**
     * Get user's current borrowings (mahasiswa)
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
            'message' => 'Your borrowings retrieved successfully',
            'data' => $borrowings
        ]);
    }

    /**
     * Get user's borrowing history (mahasiswa)
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

        $sortField = $request->get('sort_field', 'returned_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 10);

        $borrowings = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Borrowing history retrieved successfully',
            'data' => $borrowings
        ]);
    }

    /**
     * Extend borrowing period (mahasiswa)
     */
    public function extend($id)
    {
        $borrowing = Borrowing::with(['book.category'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing not found'
            ], 404);
        }

        $user = auth()->user();
        if ($borrowing->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($borrowing->status !== 'borrowed') {
            return response()->json([
                'success' => false,
                'message' => 'Only borrowed books can be extended'
            ], 400);
        }

        // Check if already extended
        if ($borrowing->is_extended) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing period already extended'
            ], 400);
        }

        // Check if within extension window (max 3 days before due date)
        $daysBeforeDue = $borrowing->due_date->diffInDays(now(), false);
        if ($daysBeforeDue > -3) {
            return response()->json([
                'success' => false,
                'message' => 'Extension can only be requested up to 3 days before due date',
                'days_before_due' => $daysBeforeDue,
                'required_days_before_due' => '3 or more'
            ], 400);
        }

        // Extend by half of original period, maksimal total 7 hari
        $originalDays = $borrowing->book->category->max_borrow_days;
        $extensionDays = min(ceil($originalDays / 2), 7); // Maksimal 7 hari total

        // Cek apakah extension melebihi maksimal 7 hari dari borrow_date
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
                    'message' => 'Cannot extend. Maximum 7-day borrowing period reached.'
                ], 400);
            }

            $extensionDays = $allowedExtension;
            $newDueDate = $borrowing->due_date->copy()->addDays($extensionDays);
        }

        $borrowing->due_date = $newDueDate;
        $borrowing->is_extended = true;
        $borrowing->extended_at = now();
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Borrowing period extended successfully',
            'data' => $borrowing,
            'extension_details' => [
                'original_due_date' => $borrowing->due_date->format('Y-m-d'),
                'new_due_date' => $newDueDate->format('Y-m-d'),
                'extension_days' => $extensionDays,
                'total_borrow_days' => $totalBorrowDays,
                'max_total_days' => 7
            ]
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create fine for late return
     */
    private function createFine(Borrowing $borrowing)
    {
        // Calculate late days
        $lateDays = $borrowing->due_date->diffInDays($borrowing->returned_at, false);
        if ($lateDays <= 0) return;

        // Fine calculation: Rp 1,000 per day
        $finePerDay = 1000;
        $amount = $lateDays * $finePerDay;

        // Create fine
        $fine = Fine::create([
            'borrowing_id' => $borrowing->id,
            'user_id' => $borrowing->user_id,
            'amount' => $amount,
            'late_days' => $lateDays,
            'status' => 'unpaid',
            'due_date' => now()->addDays(7)
        ]);

        return $fine;
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
        }

        return response()->json([
            'success' => true,
            'message' => 'Late borrowings updated',
            'count' => $lateBorrowings->count()
        ]);
    }
}
