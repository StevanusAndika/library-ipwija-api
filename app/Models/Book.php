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

    // Always include public URLs in JSON responses
    protected $appends = [
        'cover_image_url',
        'ebook_url',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    // ==================== BORROWING HISTORY METHODS ====================

    /**
     * Get current active borrowings (approved, borrowed, late)
     */
    public function currentBorrowings()
    {
        return $this->borrowings()->whereIn('status', ['approved', 'borrowed', 'late']);
    }

    /**
     * Get returned borrowings (history)
     */
    public function returnedBorrowings()
    {
        return $this->borrowings()->where('status', 'returned');
    }

    /**
     * Get pending borrowings
     */
    public function pendingBorrowings()
    {
        return $this->borrowings()->where('status', 'pending');
    }

    /**
     * Get all borrowings with user details
     */
    public function allBorrowingsWithUsers()
    {
        return $this->borrowings()->with('user');
    }

    /**
     * Get current borrowers
     */
    public function currentBorrowers()
    {
        return $this->currentBorrowings()->with('user')->get()->map(function($borrowing) {
            return [
                'user' => $borrowing->user,
                'borrowing' => [
                    'id' => $borrowing->id,
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date,
                    'due_date' => $borrowing->due_date,
                    'return_date' => $borrowing->return_date,
                ]
            ];
        });
    }

    /**
     * Get borrowing statistics for this book
     */
    public function getBorrowingStats()
    {
        return [
            'total_borrowings' => $this->borrowings()->count(),
            'current_borrowings' => $this->currentBorrowings()->count(),
            'returned_borrowings' => $this->returnedBorrowings()->count(),
            'pending_borrowings' => $this->pendingBorrowings()->count(),
            'late_borrowings' => $this->borrowings()->where('status', 'late')->count(),
            'popularity_rank' => $this->calculatePopularityRank(),
        ];
    }

    /**
     * Calculate book popularity based on borrowings
     */
    private function calculatePopularityRank()
    {
        $totalBorrowings = $this->borrowings()->count();
        $recentBorrowings = $this->borrowings()->where('created_at', '>=', now()->subMonths(3))->count();

        // Simple popularity calculation
        $score = ($totalBorrowings * 1) + ($recentBorrowings * 2);

        if ($score >= 10) return 'very_popular';
        if ($score >= 5) return 'popular';
        if ($score >= 2) return 'moderate';
        return 'new';
    }

    /**
     * Get borrowing timeline
     */
    public function getBorrowingTimeline($limit = 10)
    {
        return $this->borrowings()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'user_name' => $borrowing->user->name,
                    'user_nim' => $borrowing->user->nim,
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                    'is_late' => $borrowing->isOverdue(),
                    'created_at' => $borrowing->created_at->format('d-m-Y H:i'),
                ];
            });
    }

    // ==================== HELPER METHODS ====================

    public function isAvailable()
    {
        return $this->status == 1 && $this->available_stock > 0;
    }

    public function isSoftCopy()
    {
        return $this->book_type === 'softcopy';
    }

    public function canBeBorrowed()
    {
        return $this->isAvailable() &&
               $this->category &&
               $this->category->can_borrow &&
               $this->category->status == 1;
    }

    public function decrementAvailableStock()
    {
        if ($this->available_stock > 0) {
            $this->available_stock -= 1;
            $this->save();
            return true;
        }
        return false;
    }

    public function incrementAvailableStock()
    {
        if ($this->available_stock < $this->stock) {
            $this->available_stock += 1;
            $this->save();
            return true;
        }
        return false;
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 1)->where('available_stock', '>', 0);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('title', 'like', "%{$search}%")
            ->orWhere('author', 'like', "%{$search}%")
            ->orWhere('isbn', 'like', "%{$search}%");
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        return $this->status == 1 ? 'available' : 'unavailable';
    }

    public function getIsActiveAttribute()
    {
        return $this->status == 1;
    }

    public function getCoverImageUrlAttribute()
    {
        if ($this->cover_image) {
            return asset('storage/' . $this->cover_image);
        }
        return asset('images/default-book-cover.jpg');
    }

    public function getEbookUrlAttribute()
    {
        if ($this->file_path && $this->isSoftCopy()) {
            return asset('storage/' . $this->file_path);
        }
        return null;
    }

    // For API response
    public function toApiResponse($includeHistory = false)
    {
        $data = [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'slug' => $this->slug,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'publication_year' => $this->publication_year,
            'stock' => $this->stock,
            'available_stock' => $this->available_stock,
            'book_type' => $this->book_type,
            'description' => $this->description,
            'synopsis' => $this->synopsis,
            'pages' => $this->pages,
            'language' => $this->language,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'is_available' => $this->isAvailable(),
            'cover_image_url' => $this->cover_image_url,
            'ebook_url' => $this->ebook_url,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'borrowing_stats' => $this->getBorrowingStats(),
        ];

        if ($includeHistory) {
            $data['borrowing_history'] = $this->getBorrowingTimeline(5);
            $data['current_borrowers'] = $this->currentBorrowers();
        }

        return $data;
    }
}
