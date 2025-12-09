<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'extended_date' => 'date',
        'extended_due_date' => 'date',
        'is_extended' => 'boolean',
        'fine_paid' => 'boolean',
        'fine_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($borrowing) {
            // Generate borrow_code jika kosong
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

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['approved', 'borrowed', 'late']);
    }

    // Helper methods
    public function isOverdue()
    {
        return $this->status === 'late' || ($this->due_date < now() && $this->status === 'borrowed');
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
        return $this->fine_amount + ($this->days_late * 1000); // Rp 1000 per hari
    }

    public function canBeExtended()
    {
        return $this->status === 'borrowed'
            && !$this->is_extended
            && $this->due_date->diffInDays(now(), false) <= -3; // Minimal 3 hari sebelum jatuh tempo
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
}
