<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nim',
        'phone',
        'role',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat_asal',
        'alamat_sekarang',
        'foto',
        'is_anggota',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
            'is_anggota' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get all borrowings for this user
     */
    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    /**
     * Get active borrowings (approved, borrowed, late)
     */
    public function activeBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->whereIn('status', ['approved', 'borrowed', 'late']);
    }

    /**
     * Get returned borrowings
     */
    public function returnedBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->where('status', 'returned');
    }

    /**
     * Get pending borrowings
     */
    public function pendingBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->where('status', 'pending');
    }

    /**
     * Get late borrowings
     */
    public function lateBorrowings()
    {
        return $this->hasMany(Borrowing::class)
            ->where('status', 'late');
    }

    /**
     * Get all fines for this user
     */
    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    /**
     * Get unpaid fines
     */
    public function unpaidFines()
    {
        return $this->hasMany(Fine::class)
            ->where('status', 'unpaid');
    }

    /**
     * Get paid fines
     */
    public function paidFines()
    {
        return $this->hasMany(Fine::class)
            ->where('status', 'paid');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is mahasiswa
     */
    public function isMahasiswa(): bool
    {
        return $this->role === 'mahasiswa';
    }

    /**
     * Check if user has late returns
     */
    public function hasLateReturns(): bool
    {
        return $this->lateBorrowings()->exists();
    }

    /**
     * Check if user has pending approval
     */
    public function hasPendingApproval(): bool
    {
        return $this->pendingBorrowings()->exists();
    }

    /**
     * Count active borrowings
     */
    public function activeBorrowCount(): int
    {
        return $this->activeBorrowings()->count();
    }

    /**
     * Check if user has unpaid fines
     */
    public function hasUnpaidFines(): bool
    {
        return $this->unpaidFines()->exists();
    }

    /**
     * Get total unpaid fines amount
     */
    public function getTotalUnpaidFines(): float
    {
        return $this->unpaidFines()->sum('amount');
    }

    /**
     * Check if user can borrow (validation rules)
     */
    public function canBorrow(): bool
    {
        // User must be mahasiswa
        if (!$this->isMahasiswa()) {
            return false;
        }

        // User must be active member
        if (!$this->is_anggota || !$this->is_active) {
            return false;
        }

        // User must not have unpaid fines
        if ($this->hasUnpaidFines()) {
            return false;
        }

        // User must not have late returns
        if ($this->hasLateReturns()) {
            return false;
        }

        // User must not exceed max active borrowings (max 2)
        if ($this->activeBorrowCount() >= 2) {
            return false;
        }

        return true;
    }

    /**
     * Get user's borrowing history
     */
    public function borrowingHistory()
    {
        return $this->borrowings()
            ->with(['book.category'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get user's current borrowings
     */
    public function currentBorrowings()
    {
        return $this->activeBorrowings()
            ->with(['book.category'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get user's fine history
     */
    public function fineHistory()
    {
        return $this->fines()
            ->with(['borrowing.book'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

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
            'total_fine_amount' => $this->fines()->sum('amount'),
            'unpaid_fine_amount' => $this->getTotalUnpaidFines(),
        ];
    }

    /**
     * Format user data for API response
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'nim' => $this->nim,
            'phone' => $this->phone,
            'role' => $this->role,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir?->format('Y-m-d'),
            'agama' => $this->agama,
            'alamat_asal' => $this->alamat_asal,
            'alamat_sekarang' => $this->alamat_sekarang,
            'foto' => $this->foto ? asset('storage/' . $this->foto) : null,
            'is_anggota' => $this->is_anggota,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'stats' => $this->getStats(),
            'can_borrow' => $this->canBorrow(),
        ];
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for anggota
     */
    public function scopeAnggota($query)
    {
        return $query->where('is_anggota', true);
    }

    /**
     * Scope for admin users
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope for mahasiswa users
     */
    public function scopeMahasiswa($query)
    {
        return $query->where('role', 'mahasiswa');
    }

    /**
     * Search users by name, email, or nim
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('nim', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
