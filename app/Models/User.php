<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nim',
        'phone',
        'address',
        'tempat_lahir',
        'tanggal_lahir',
        'gender',
        'agama',
        'status',
        'profile_picture',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
        ];
    }

    // ==================== RELATIONSHIPS ====================
    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    public function activeBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->whereIn('status', ['approved', 'borrowed']);
    }

    public function returnedBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->where('status', 'returned');
    }

    public function pendingBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->where('status', 'pending');
    }

    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    public function unpaidFines()
    {
        return $this->hasMany(Fine::class)
            ->where('status', 'unpaid');
    }

    public function paidFines()
    {
        return $this->hasMany(Fine::class)
            ->where('status', 'paid');
    }

    // ==================== BASIC VALIDATION METHODS ====================
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function hasUnpaidFines(): bool
    {
        return $this->unpaidFines()->exists();
    }

    public function hasLateReturns(): bool
    {
        return $this->borrowings()->where('status', 'late')->exists();
    }

    public function activeBorrowCount(): int
    {
        return $this->activeBorrowings()->count();
    }

    public function getTotalUnpaidFines(): float
    {
        return $this->unpaidFines()->sum('amount');
    }

    // FIX: Added canBorrow method
    public function canBorrow(): bool
    {
        return $this->canBorrowAnyBook()['can_borrow'];
    }

    // ==================== STRICT BORROWING VALIDATION ====================

    /**
     * Strict check: User cannot borrow ANY new books
     */
    public function canBorrowAnyBook(): array
    {
        $reasons = [];
        $canBorrow = true;

        // 1. Check if account is active
        if (!$this->isActive()) {
            $canBorrow = false;
            $reasons[] = [
                'type' => 'account_inactive',
                'message' => 'Akun Anda tidak aktif',
                'detail' => 'Status akun: ' . $this->status
            ];
        }

        // 2. Check for unpaid fines
        if ($this->hasUnpaidFines()) {
            $canBorrow = false;
            $reasons[] = [
                'type' => 'unpaid_fines',
                'message' => 'Anda memiliki denda yang belum dibayar',
                'detail' => 'Total denda: Rp ' . number_format($this->getTotalUnpaidFines(), 0, ',', '.')
            ];
        }

        // 3. Check for late returns
        if ($this->hasLateReturns()) {
            $canBorrow = false;
            $lateCount = $this->borrowings()->where('status', 'late')->count();
            $reasons[] = [
                'type' => 'late_returns',
                'message' => 'Anda memiliki buku yang terlambat dikembalikan',
                'detail' => 'Jumlah buku terlambat: ' . $lateCount . ' buku'
            ];
        }

        // 4. Check max borrowing limit (2 books)
        if ($this->activeBorrowCount() >= 2) {
            $canBorrow = false;
            $reasons[] = [
                'type' => 'max_limit',
                'message' => 'Anda sudah mencapai batas maksimal peminjaman',
                'detail' => 'Anda sudah meminjam ' . $this->activeBorrowCount() . ' dari 2 buku maksimal'
            ];
        }

        // 5. Check for books approaching due date (within 2 days)
        $approachingDueCount = $this->borrowings()
            ->where('status', 'borrowed')
            ->where('due_date', '<=', now()->addDays(2))
            ->where('due_date', '>', now())
            ->count();

        if ($approachingDueCount > 0) {
            $canBorrow = false;
            $reasons[] = [
                'type' => 'approaching_due',
                'message' => 'Anda memiliki buku yang hampir jatuh tempo',
                'detail' => 'Jumlah buku hampir jatuh tempo: ' . $approachingDueCount . ' buku'
            ];
        }

        return [
            'can_borrow' => $canBorrow,
            'message' => $canBorrow ? 'Anda dapat meminjam buku' : 'ANDA TIDAK DAPAT MEMINJAM BUKU BARU',
            'reasons' => $reasons,
            'total_reasons' => count($reasons),
            'blocking_details' => $this->getBlockingBorrowingsDetails()
        ];
    }

    /**
     * Get detailed blocking information
     */
    public function getBlockingBorrowingsDetails(): array
    {
        $details = [];

        // Get all overdue books
        $overdueBooks = $this->borrowings()
            ->where('status', 'late')
            ->with('book')
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'book_title' => $borrowing->book->title,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'days_overdue' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) * -1 : 0,
                    'fine_amount' => $borrowing->fine_amount,
                    'fine_paid' => $borrowing->fine_paid
                ];
            });

        if ($overdueBooks->count() > 0) {
            $details['overdue_books'] = $overdueBooks;
            $details['total_overdue'] = $overdueBooks->count();
        }

        // Get books approaching due date
        $approachingBooks = $this->borrowings()
            ->where('status', 'borrowed')
            ->where('due_date', '<=', now()->addDays(2))
            ->where('due_date', '>', now())
            ->with('book')
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'book_title' => $borrowing->book->title,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'days_remaining' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) : 0
                ];
            });

        if ($approachingBooks->count() > 0) {
            $details['approaching_due_books'] = $approachingBooks;
            $details['total_approaching'] = $approachingBooks->count();
        }

        // Get all active borrowings
        $allActive = $this->activeBorrowings()
            ->with('book')
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'book_title' => $borrowing->book->title,
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'is_overdue' => $borrowing->isOverdue(),
                    'days_remaining' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) : null
                ];
            });

        $details['all_active_borrowings'] = $allActive;
        $details['total_active'] = $allActive->count();

        return $details;
    }

    // ==================== BOOK-SPECIFIC VALIDATION ====================

    /**
     * Check if user has unreturned copy of this book
     */
    public function hasUnreturnedBook($bookId): bool
    {
        return $this->borrowings()
            ->where('book_id', $bookId)
            ->whereIn('status', ['pending', 'approved', 'borrowed', 'late'])
            ->exists();
    }

    /**
     * Get last borrowing of this book
     */
    public function getLastBorrowingOfBook($bookId)
    {
        return $this->borrowings()
            ->where('book_id', $bookId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Complete book borrowing check
     */
    public function canBorrowBook($bookId): array
    {
        // First, check if user can borrow ANY book
        $anyBookCheck = $this->canBorrowAnyBook();
        if (!$anyBookCheck['can_borrow']) {
            return array_merge($anyBookCheck, [
                'book_specific' => false,
                'message' => 'ANDA TIDAK DAPAT MEMINJAM BUKU BARU KARENA MASALAH PEMINJAMAN SAAT INI'
            ]);
        }

        // Then check book-specific restrictions
        $reasons = [];
        $canBorrow = true;

        // Check if user already has this book
        if ($this->hasUnreturnedBook($bookId)) {
            $canBorrow = false;
            $lastBorrowing = $this->getLastBorrowingOfBook($bookId);
            $reasons[] = [
                'type' => 'already_borrowed',
                'message' => 'Anda masih memiliki buku ini',
                'detail' => 'Status: ' . ($lastBorrowing ? $lastBorrowing->status : 'N/A')
            ];
        }

        // Check if user has borrowed this book before
        $hasBorrowedBefore = $this->borrowings()
            ->where('book_id', $bookId)
            ->exists();

        return [
            'can_borrow' => $canBorrow,
            'message' => $canBorrow ? 'Anda dapat meminjam buku ini' : 'Tidak dapat meminjam buku ini',
            'reasons' => $reasons,
            'last_borrowing' => $this->hasUnreturnedBook($bookId) ? $this->getLastBorrowingOfBook($bookId) : null,
            'book_specific' => true,
            'has_borrowed_before' => $hasBorrowedBefore,
            'has_unreturned_copy' => $this->hasUnreturnedBook($bookId)
        ];
    }

    /**
     * Get user's borrowing status for a specific book
     */
    public function getBookBorrowingStatus($bookId): array
    {
        $currentBorrowing = $this->getLastBorrowingOfBook($bookId);
        $hasUnreturned = $this->hasUnreturnedBook($bookId);
        $hasBorrowedBefore = $this->borrowings()->where('book_id', $bookId)->exists();
        $canBorrowCheck = $this->canBorrowBook($bookId);

        return [
            'has_unreturned_copy' => $hasUnreturned,
            'has_borrowed_before' => $hasBorrowedBefore,
            'can_borrow' => $canBorrowCheck['can_borrow'],
            'current_borrowing' => $currentBorrowing ? [
                'id' => $currentBorrowing->id,
                'status' => $currentBorrowing->status,
                'borrow_date' => $currentBorrowing->borrow_date ? $currentBorrowing->borrow_date->format('d-m-Y') : null,
                'due_date' => $currentBorrowing->due_date ? $currentBorrowing->due_date->format('d-m-Y') : null,
                'return_date' => $currentBorrowing->return_date ? $currentBorrowing->return_date->format('d-m-Y') : null,
                'is_overdue' => $currentBorrowing->isOverdue(),
                'days_overdue' => $currentBorrowing->isOverdue() ?
                    ($currentBorrowing->due_date ? now()->diffInDays($currentBorrowing->due_date, false) * -1 : 0) : 0
            ] : null,
            'total_times_borrowed' => $this->borrowings()->where('book_id', $bookId)->count(),
            'successful_returns' => $this->borrowings()->where('book_id', $bookId)->where('status', 'returned')->count(),
            'blocking_issues' => $canBorrowCheck['reasons']
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * Calculate user's statistics
     */
    public function getStats(): array
    {
        return [
            'total_borrowings' => $this->borrowings()->count(),
            'active_borrowings' => $this->activeBorrowCount(),
            'total_fines' => $this->fines()->count(),
            'unpaid_fines' => $this->unpaidFines()->count(),
            'unpaid_fine_amount' => $this->getTotalUnpaidFines(),
            'successful_returns' => $this->returnedBorrowings()->count(),
            'late_returns' => $this->borrowings()->where('status', 'late')->count(),
            'pending_requests' => $this->pendingBorrowings()->count(),
        ];
    }

    /**
     * Get user's recent borrowing activity
     */
    public function getRecentActivity($limit = 10)
    {
        return $this->borrowings()
            ->with('book')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($borrowing) {
                return [
                    'book_title' => $borrowing->book->title,
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                    'is_overdue' => $borrowing->isOverdue(),
                ];
            });
    }

    /**
     * Format user data for API response
     */
    public function toApiResponse(): array
    {
        $borrowStatus = $this->canBorrowAnyBook();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'nim' => $this->nim,
            'phone' => $this->phone,
            'address' => $this->address,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir ? $this->tanggal_lahir->format('Y-m-d') : null,
            'gender' => $this->gender,
            'agama' => $this->agama,
            'status' => $this->status,
            'profile_picture' => $this->profile_picture ? asset('storage/' . $this->profile_picture) : null,
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'can_borrow_any_book' => $borrowStatus['can_borrow'],
            'borrow_status' => $borrowStatus,
            'stats' => $this->getStats(),
            'active_borrowings_count' => $this->activeBorrowCount(),
            'has_unpaid_fines' => $this->hasUnpaidFines(),
            'has_late_returns' => $this->hasLateReturns(),
        ];
    }

    // ==================== SCOPES ====================
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeRegular($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('nim', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
