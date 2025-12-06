<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrowing_id',
        'user_id',
        'amount',
        'late_days',
        'fine_date',
        'paid_date',
        'status',
        'description',
    ];

    protected $casts = [
        'fine_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function borrowing()
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->paid_date = now();
        $this->save();

        if ($this->borrowing) {
            $this->borrowing->fine_paid = true;
            $this->borrowing->save();
        }
    }
}
