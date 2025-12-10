<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Borrowing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'borrow_code',
        'user_id',
        'book_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        'is_extended',
        'extended_date',
        'extended_due_date',
        'late_days',
        'fine_amount',
        'fine_paid',
        'notes',
        'approved_at',
        'borrowed_at',
        'returned_at',
        'rejection_reason',
        'rejected_at',
        'extended_at'
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'extended_date' => 'date',
        'extended_due_date' => 'date',
        'approved_at' => 'datetime',
        'borrowed_at' => 'datetime',
        'returned_at' => 'datetime',
        'rejected_at' => 'datetime',
        'extended_at' => 'datetime',
        'is_extended' => 'boolean',
        'fine_paid' => 'boolean',
        'fine_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($borrowing) {
            if (empty($borrowing->borrow_code)) {
                $borrowing->borrow_code = 'BOR-' . strtoupper(Str::random(6)) . '-' . date('Ymd');
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function fine()
    {
        return $this->hasOne(Fine::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeBorrowed($query)
    {
        return $query->where('status', 'borrowed');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['approved', 'borrowed', 'late']);
    }

    // ==================== AUTO-UPDATE KE LATE ====================

    /**
     * Check and automatically update to late status if overdue
     * Call this method whenever you fetch borrowing data
     */
    public function autoUpdateIfOverdue()
    {
        // Jika status borrowed dan sudah lewat due_date
        if ($this->status === 'borrowed' && $this->due_date && $this->due_date < now()) {
            $lateDays = now()->diffInDays($this->due_date, false) * -1;

            if ($lateDays > 0) {
                DB::beginTransaction();
                try {
                    // Update status ke late
                    $oldStatus = $this->status;
                    $this->status = 'late';
                    $this->late_days = $lateDays;

                    // Hitung denda
                    $fineAmount = $lateDays * 1000;
                    $this->fine_amount = $fineAmount;
                    $this->fine_paid = false;

                    $this->save();

                    // Buat record denda jika belum ada
                    if (!$this->fine) {
                        Fine::create([
                            'borrowing_id' => $this->id,
                            'user_id' => $this->user_id,
                            'amount' => $fineAmount,
                            'late_days' => $lateDays,
                            'fine_date' => now(),
                            'status' => 'unpaid',
                            'description' => 'Denda keterlambatan otomatis untuk buku: ' . ($this->book ? $this->book->title : 'Unknown Book'),
                            'notes' => json_encode([
                                'auto_generated' => true,
                                'previous_status' => $oldStatus,
                                'due_date' => $this->due_date->format('Y-m-d'),
                                'checked_date' => now()->format('Y-m-d H:i:s'),
                                'late_days' => $lateDays
                            ])
                        ]);
                    }

                    DB::commit();

                    // Log perubahan
                    Log::info("Borrowing #{$this->id} auto-updated from {$oldStatus} to LATE. Late days: {$lateDays}");

                    return [
                        'updated' => true,
                        'old_status' => $oldStatus,
                        'new_status' => 'late',
                        'late_days' => $lateDays,
                        'fine_amount' => $fineAmount
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Failed to auto-update borrowing #{$this->id} to late: " . $e->getMessage());
                    return [
                        'updated' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        return ['updated' => false];
    }

    /**
     * Static method to check and update all overdue borrowings
     */
    public static function checkAllOverdueBorrowings()
    {
        $borrowings = self::where('status', 'borrowed')
            ->where('due_date', '<', now())
            ->get();

        $results = [
            'total_checked' => $borrowings->count(),
            'updated' => 0,
            'errors' => []
        ];

        foreach ($borrowings as $borrowing) {
            $result = $borrowing->autoUpdateIfOverdue();
            if ($result['updated']) {
                $results['updated']++;
            } elseif (isset($result['error'])) {
                $results['errors'][] = [
                    'borrowing_id' => $borrowing->id,
                    'error' => $result['error']
                ];
            }
        }

        Log::info("Auto-check overdue borrowings completed. Updated: {$results['updated']}/{$results['total_checked']}");

        return $results;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if borrowing is overdue (with auto-update)
     */
    public function isOverdue()
    {
        // Auto-update jika perlu
        if ($this->status === 'borrowed' && $this->due_date && $this->due_date < now()) {
            $this->autoUpdateIfOverdue();
        }

        return $this->status === 'late' || ($this->due_date && $this->due_date < now());
    }

    public function getDaysLateAttribute()
    {
        if ($this->status === 'late' || $this->return_date) {
            $dueDate = $this->due_date ?: $this->extended_due_date;
            $compareDate = $this->return_date ?: now();

            return max(0, $dueDate->diffInDays($compareDate, false));
        }

        return 0;
    }

    public function getTotalFineAttribute()
    {
        $daysLate = $this->days_late;
        $autoFine = $daysLate * 1000;

        // Return yang lebih besar antara fine_amount dan auto-calculated
        return max($this->fine_amount, $autoFine);
    }

    public function canBeExtended()
    {
        return $this->status === 'borrowed'
            && !$this->is_extended
            && $this->due_date->diffInDays(now(), false) <= -3;
    }

    public function extend($days = 3)
    {
        if (!$this->canBeExtended()) {
            return false;
        }

        $this->is_extended = true;
        $this->extended_date = now();
        $this->extended_due_date = $this->due_date->copy()->addDays($days);
        $this->due_date = $this->extended_due_date;
        $this->save();

        return true;
    }

    // Status methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isBorrowed()
    {
        return $this->status === 'borrowed';
    }

    public function isLate()
    {
        return $this->status === 'late';
    }

    public function isReturned()
    {
        return $this->status === 'returned';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Get borrowing with auto-update status
     */
    public function getWithAutoUpdate()
    {
        $this->autoUpdateIfOverdue();
        return $this;
    }

    /**
     * Calculate days remaining (negative if overdue)
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->due_date || $this->status === 'returned') {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get formatted status with color
     */
    public function getStatusFormattedAttribute()
    {
        $status = $this->status;
        $colors = [
            'pending' => 'warning',
            'approved' => 'info',
            'borrowed' => 'primary',
            'late' => 'danger',
            'returned' => 'success',
            'rejected' => 'secondary',
            'cancelled' => 'dark'
        ];

        return [
            'text' => ucfirst($status),
            'color' => $colors[$status] ?? 'secondary'
        ];
    }

    /**
     * Check if fine needs to be paid
     */
    public function needsFinePayment()
    {
        return $this->fine_amount > 0 && !$this->fine_paid;
    }

    // Format for API
    public function toApiResponse()
    {
        // Auto-update sebelum return data
        $updateResult = $this->autoUpdateIfOverdue();

        return [
            'id' => $this->id,
            'borrow_code' => $this->borrow_code,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'nim' => $this->user->nim,
                'email' => $this->user->email
            ] : null,
            'book' => $this->book ? [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'author' => $this->book->author,
                'isbn' => $this->book->isbn,
                'category' => $this->book->category ? $this->book->category->name : null
            ] : null,
            'borrow_date' => $this->borrow_date ? $this->borrow_date->format('Y-m-d') : null,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'return_date' => $this->return_date ? $this->return_date->format('Y-m-d') : null,
            'status' => $this->status,
            'status_formatted' => $this->status_formatted,
            'is_extended' => $this->is_extended,
            'extended_date' => $this->extended_date ? $this->extended_date->format('Y-m-d') : null,
            'extended_due_date' => $this->extended_due_date ? $this->extended_due_date->format('Y-m-d') : null,
            'late_days' => $this->late_days,
            'fine_amount' => $this->fine_amount,
            'fine_paid' => $this->fine_paid,
            'notes' => $this->notes,
            'approved_at' => $this->approved_at ? $this->approved_at->format('Y-m-d H:i:s') : null,
            'borrowed_at' => $this->borrowed_at ? $this->borrowed_at->format('Y-m-d H:i:s') : null,
            'returned_at' => $this->returned_at ? $this->returned_at->format('Y-m-d H:i:s') : null,
            'rejected_at' => $this->rejected_at ? $this->rejected_at->format('Y-m-d H:i:s') : null,
            'rejection_reason' => $this->rejection_reason,
            'extended_at' => $this->extended_at ? $this->extended_at->format('Y-m-d H:i:s') : null,
            'days_late' => $this->days_late,
            'days_remaining' => $this->days_remaining,
            'total_fine' => $this->total_fine,
            'is_overdue' => $this->isOverdue(),
            'can_be_extended' => $this->canBeExtended(),
            'needs_fine_payment' => $this->needsFinePayment(),
            'auto_update_result' => $updateResult,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Scope to get borrowings that need auto-update
     */
    public function scopeNeedsAutoUpdate($query)
    {
        return $query->where('status', 'borrowed')
            ->where('due_date', '<', now());
    }

    /**
     * Bulk update all overdue borrowings
     */
    public static function bulkUpdateOverdue()
    {
        $results = self::checkAllOverdueBorrowings();

        return [
            'success' => true,
            'message' => 'Auto-update completed',
            'data' => $results
        ];
    }
}
