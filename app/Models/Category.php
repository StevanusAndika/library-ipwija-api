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
        'status', // GANTI is_active menjadi status
    ];

    protected $casts = [
        'max_borrow_days' => 'integer',
        'can_borrow' => 'boolean',
    ];

    // Accessor untuk is_active (kompatibilitas)
    public function getIsActiveAttribute()
    {
        return $this->status == 1;
    }

    // Scope untuk query
    public function scopeWhereIsActive($query, $active = true)
    {
        return $query->where('status', $active ? 1 : 0);
    }

    // Relationship
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    public function activeBooks()
    {
        return $this->books()->where('status', 1);
    }

    public function isResearchCategory()
    {
        return $this->type === 'penelitian';
    }

    public function canBeBorrowed()
    {
        return $this->can_borrow && $this->status == 1;
    }
}
