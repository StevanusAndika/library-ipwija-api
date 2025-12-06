<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Book;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BorrowingController extends Controller
{
    public function index(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('borrow_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('borrow_code', 'like', "%{$search}%")
                  ->orWhereHas('book', function($q2) use ($search) {
                      $q2->where('title', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('nim', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $borrowings = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Borrowings retrieved successfully',
            'data' => $borrowings
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:books,id',
            'borrow_date' => 'required|date|after_or_equal:today',
            'due_date' => 'required|date|after:borrow_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($user->hasUnpaidFines()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot borrow books while having unpaid fines',
                'data' => null
            ], 400);
        }

        if ($user->hasLateReturns()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot borrow books while having late returns',
                'data' => null
            ], 400);
        }

        if ($user->activeBorrowCount() >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 2 books can be borrowed at once',
                'data' => null
            ], 400);
        }

        $book = Book::with('category')->find($request->book_id);

        if (!$book || !$book->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Book not available',
                'data' => null
            ], 404);
        }

        if (!$book->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Book is out of stock',
                'data' => null
            ], 400);
        }

        if (!$book->canBeBorrowed()) {
            return response()->json([
                'success' => false,
                'message' => 'This book cannot be borrowed',
                'data' => null
            ], 400);
        }

        if ($book->category->isResearchCategory() && $book->book_type === 'hardcopy') {
            return response()->json([
                'success' => false,
                'message' => 'Research books cannot be borrowed',
                'data' => null
            ], 400);
        }

        $borrowDate = Carbon::parse($request->borrow_date);
        $dueDate = Carbon::parse($request->due_date);
        $maxDays = $book->category->max_borrow_days;

        if ($dueDate->diffInDays($borrowDate) > $maxDays) {
            return response()->json([
                'success' => false,
                'message' => "Maximum borrowing period is {$maxDays} days for this category",
                'data' => null
            ], 400);
        }

        $borrowing = Borrowing::create([
            'borrow_code' => 'BOR' . Str::upper(Str::random(8)),
            'user_id' => $user->id,
            'book_id' => $book->id,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Borrowing request submitted successfully',
            'data' => $borrowing->load(['user', 'book.category'])
        ], 201);
    }

    public function approve($id)
    {
        $borrowing = Borrowing::with(['user', 'book'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing request not found',
                'data' => null
            ], 404);
        }

        if ($borrowing->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing request is not pending',
                'data' => null
            ], 400);
        }

        if ($borrowing->user->hasUnpaidFines()) {
            return response()->json([
                'success' => false,
                'message' => 'User has unpaid fines',
                'data' => null
            ], 400);
        }

        if ($borrowing->user->hasLateReturns()) {
            return response()->json([
                'success' => false,
                'message' => 'User has late returns',
                'data' => null
            ], 400);
        }

        if (!$borrowing->book->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Book is no longer available',
                'data' => null
            ], 400);
        }

        $borrowing->status = 'approved';
        $borrowing->save();

        $borrowing->book->updateAvailableStock();

        return response()->json([
            'success' => true,
            'message' => 'Borrowing request approved',
            'data' => $borrowing->load(['user', 'book.category'])
        ]);
    }

    public function reject($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|max:500',
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
                'message' => 'Borrowing request not found',
                'data' => null
            ], 404);
        }

        if ($borrowing->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing request is not pending',
                'data' => null
            ], 400);
        }

        $borrowing->status = 'rejected';
        $borrowing->notes = $request->notes;
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Borrowing request rejected',
            'data' => $borrowing
        ]);
    }

    public function markAsBorrowed($id)
    {
        $borrowing = Borrowing::with('book')->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing record not found',
                'data' => null
            ], 404);
        }

        if ($borrowing->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing is not approved yet',
                'data' => null
            ], 400);
        }

        $borrowing->status = 'borrowed';
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Book marked as borrowed',
            'data' => $borrowing
        ]);
    }

    public function returnBook($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'return_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $borrowing = Borrowing::with(['user', 'book'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing record not found',
                'data' => null
            ], 404);
        }

        if (!in_array($borrowing->status, ['borrowed', 'late'])) {
            return response()->json([
                'success' => false,
                'message' => 'Book is not currently borrowed',
                'data' => null
            ], 400);
        }

        $returnDate = Carbon::parse($request->return_date);
        $dueDate = $borrowing->extended_due_date ?? $borrowing->due_date;

        $lateDays = 0;
        $fineAmount = 0;

        if ($returnDate->greaterThan($dueDate)) {
            $lateDays = $returnDate->diffInDays($dueDate);
            $fineAmount = $lateDays * 1000;

            $borrowing->user->fines()->create([
                'borrowing_id' => $borrowing->id,
                'amount' => $fineAmount,
                'late_days' => $lateDays,
                'fine_date' => $returnDate,
                'description' => 'Denda keterlambatan pengembalian buku',
            ]);
        }

        $borrowing->return_date = $returnDate;
        $borrowing->status = 'returned';
        $borrowing->late_days = $lateDays;
        $borrowing->fine_amount = $fineAmount;
        $borrowing->notes = $request->notes;
        $borrowing->save();

        $borrowing->book->updateAvailableStock();

        return response()->json([
            'success' => true,
            'message' => 'Book returned successfully',
            'data' => $borrowing->load(['user', 'book.category'])
        ]);
    }

    public function extend($id, Request $request)
    {
        $user = $request->user();
        $borrowing = Borrowing::with(['book.category'])->find($id);

        if (!$borrowing) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing record not found',
                'data' => null
            ], 404);
        }

        if ($borrowing->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null
            ], 403);
        }

        if (!$borrowing->canBeExtended()) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing cannot be extended',
                'data' => null
            ], 400);
        }

        if ($borrowing->is_extended) {
            return response()->json([
                'success' => false,
                'message' => 'Borrowing already extended',
                'data' => null
            ], 400);
        }

        $maxExtensionDays = $borrowing->book->category->max_borrow_days;
        $newDueDate = Carbon::parse($borrowing->due_date)->addDays($maxExtensionDays);

        $borrowing->is_extended = true;
        $borrowing->extended_date = now();
        $borrowing->extended_due_date = $newDueDate;
        $borrowing->save();

        return response()->json([
            'success' => true,
            'message' => 'Borrowing extended successfully',
            'data' => $borrowing
        ]);
    }

    public function myBorrowings(Request $request)
    {
        $user = $request->user();

        $query = $user->borrowings()->with(['book.category']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('active')) {
            $query->whereIn('status', ['approved', 'borrowed', 'late']);
        }

        $perPage = $request->get('per_page', 10);
        $borrowings = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Your borrowings retrieved successfully',
            'data' => $borrowings
        ]);
    }

    public function borrowingHistory(Request $request)
    {
        $user = $request->user();

        $history = $user->borrowings()
            ->with(['book.category'])
            ->where('status', 'returned')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Borrowing history retrieved successfully',
            'data' => $history
        ]);
    }

    public function currentlyBorrowed(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category'])
            ->whereIn('status', ['borrowed', 'late']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('borrow_code', 'like', "%{$search}%")
                  ->orWhereHas('book', function($q2) use ($search) {
                      $q2->where('title', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('nim', 'like', "%{$search}%");
                  });
            });
        }

        $borrowings = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Currently borrowed books retrieved successfully',
            'data' => $borrowings
        ]);
    }

    public function lateReturns(Request $request)
    {
        $query = Borrowing::with(['user', 'book.category'])
            ->where('status', 'late')
            ->where('fine_paid', false);

        $lateReturns = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Late returns retrieved successfully',
            'data' => $lateReturns
        ]);
    }

    public function unpaidFines(Request $request)
    {
        $users = User::whereHas('fines', function($query) {
                $query->where('status', 'unpaid');
            })
            ->with(['fines' => function($query) {
                $query->where('status', 'unpaid');
            }])
            ->withSum(['fines' => function($query) {
                $query->where('status', 'unpaid');
            }], 'amount')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Users with unpaid fines retrieved successfully',
            'data' => $users
        ]);
    }
}
