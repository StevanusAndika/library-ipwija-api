<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'max_borrow_days',
        'can_borrow',
        'status',
    ];

    protected $casts = [
        'max_borrow_days' => 'integer',
        'can_borrow' => 'boolean',
    ];

    // Relationships
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    // Helper Methods
    public function isActive()
    {
        return $this->status == 1;
    }

    public function isResearchCategory()
    {
        return $this->type === 'penelitian';
    }

    public function canBeBorrowed()
    {
        return $this->can_borrow && $this->isActive();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeBorrowable($query)
    {
        return $query->where('can_borrow', true)->where('status', 1);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }

    // Accessors
    public function getIsActiveAttribute()
    {
        return $this->status == 1;
    }

    public function getActiveBooksCountAttribute()
    {
        return $this->books()->where('status', 1)->count();
    }

    public function getAvailableBooksCountAttribute()
    {
        return $this->books()->where('status', 1)->where('available_stock', '>', 0)->count();
    }
}
