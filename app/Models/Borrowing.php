<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'late_days' => 'integer',
        'fine_amount' => 'decimal:2',
    ];

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

    public function isLate()
    {
        if ($this->return_date) {
            return false;
        }

        $dueDate = $this->extended_due_date ?? $this->due_date;
        return now()->greaterThan($dueDate);
    }

    public function calculateLateDays()
    {
        if ($this->return_date || !$this->isLate()) {
            return 0;
        }

        $dueDate = $this->extended_due_date ?? $this->due_date;
        return now()->diffInDays($dueDate);
    }

    public function calculateFine()
    {
        $lateDays = $this->calculateLateDays();
        return $lateDays * 1000;
    }

    public function canBeExtended()
    {
        return !$this->is_extended &&
               !$this->isLate() &&
               $this->status === 'borrowed' &&
               now()->lessThanOrEqualTo($this->due_date);
    }

    public function markAsLate()
    {
        if ($this->isLate() && $this->status !== 'late') {
            $this->status = 'late';
            $this->late_days = $this->calculateLateDays();
            $this->fine_amount = $this->calculateFine();
            $this->save();

            Fine::create([
                'borrowing_id' => $this->id,
                'user_id' => $this->user_id,
                'amount' => $this->fine_amount,
                'late_days' => $this->late_days,
                'fine_date' => now(),
                'description' => 'Denda keterlambatan pengembalian buku',
            ]);
        }
    }
}
