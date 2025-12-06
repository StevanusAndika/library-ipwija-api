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
        'is_active',
    ];

    protected $casts = [
        'max_borrow_days' => 'integer',
        'can_borrow' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function books()
    {
        return $this->hasMany(Book::class);
    }

    public function activeBooks()
    {
        return $this->books()->where('is_active', true);
    }

    public function isResearchCategory()
    {
        return $this->type === 'penelitian';
    }

    public function canBeBorrowed()
    {
        return $this->can_borrow && $this->is_active;
    }
}
