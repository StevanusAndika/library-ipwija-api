<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'isbn',
        'title',
        'slug',
        'category_id',
        'author',
        'publisher',
        'publication_year',
        'stock',
        'available_stock',
        'book_type',
        'file_path',
        'cover_image',
        'description',
        'synopsis',
        'pages',
        'language',
        'status',
    ];

    protected $casts = [
        'publication_year' => 'integer',
        'stock' => 'integer',
        'available_stock' => 'integer',
        'pages' => 'integer',
        'status' => 'integer',
    ];

    // Accessor untuk is_active (kompatibilitas dengan query)
    public function getIsActiveAttribute()
    {
        return $this->status == 1;
    }

    // Scope untuk query
    public function scopeWhereIsActive($query, $active = true)
    {
        return $query->where('status', $active ? 1 : 0);
    }

    // Relationship dengan Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    public function activeBorrowings()
    {
        return $this->borrowings()->whereIn('status', ['approved', 'borrowed', 'late']);
    }

    public function isAvailable()
    {
        return $this->available_stock > 0 && $this->status == 1;
    }

    public function isSoftCopy()
    {
        return $this->book_type === 'softcopy';
    }

    public function updateAvailableStock()
    {
        $borrowedCount = $this->activeBorrowings()->count();
        $this->available_stock = max(0, $this->stock - $borrowedCount);
        $this->save();
    }

    public function canBeBorrowed()
    {
        return $this->isAvailable() && $this->category->canBeBorrowed();
    }
}
