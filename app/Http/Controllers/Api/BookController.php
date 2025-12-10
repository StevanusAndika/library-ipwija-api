<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    // ==================== PUBLIC METHODS ====================

    /**
     * Get all books (public)
     */
    public function indexPublic(Request $request)
    {
        $query = Book::with('category')->where('status', 1);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        if ($request->has('available_only')) {
            $query->where('available_stock', '>', 0);
        }

        $sortField = $request->get('sort_field', 'title');
        $sortOrder = $request->get('sort_order', 'asc');
        $perPage = $request->get('per_page', 15);

        $books = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        $formattedBooks = $books->getCollection()->map(function($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => $book->isbn,
                'publisher' => $book->publisher,
                'publication_year' => $book->publication_year,
                'stock' => $book->stock,
                'available_stock' => $book->available_stock,
                'book_type' => $book->book_type,
                'cover_image_url' => $book->cover_image_url,
                'description' => $book->description,
                'synopsis' => $book->synopsis,
                'pages' => $book->pages,
                'language' => $book->language,
                'status' => $book->status,
                'is_available' => $book->isAvailable(),
                'category' => $book->category ? [
                    'id' => $book->category->id,
                    'name' => $book->category->name,
                    'slug' => $book->category->slug,
                ] : null,
                'created_at' => $book->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $books->setCollection($formattedBooks);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully',
            'data' => $books
        ]);
    }

    /**
     * Get single book (public)
     */
    public function showPublic($id)
    {
        $book = Book::with('category')->where('status', 1)->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found or not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Book retrieved successfully',
            'data' => $book->toApiResponse(true)
        ]);
    }

    /**
     * Get book borrowing history (public)
     */
    public function borrowingHistory($id)
    {
        $book = Book::with(['category'])->where('status', 1)->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        $history = $book->borrowings()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $formattedHistory = $history->getCollection()->map(function($borrowing) {
            return [
                'id' => $borrowing->id,
                'borrow_code' => $borrowing->borrow_code,
                'user' => [
                    'id' => $borrowing->user->id,
                    'name' => $borrowing->user->name,
                    'nim' => $borrowing->user->nim,
                    'email' => $borrowing->user->email,
                ],
                'status' => $borrowing->status,
                'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                'is_late' => $borrowing->isOverdue(),
                'is_extended' => $borrowing->is_extended,
                'fine_amount' => $borrowing->fine_amount,
                'fine_paid' => $borrowing->fine_paid,
                'created_at' => $borrowing->created_at->format('d-m-Y H:i'),
                'updated_at' => $borrowing->updated_at->format('d-m-Y H:i'),
            ];
        });

        $history->setCollection($formattedHistory);

        return response()->json([
            'success' => true,
            'message' => 'Book borrowing history retrieved successfully',
            'data' => [
                'book' => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author,
                    'isbn' => $book->isbn,
                    'category' => $book->category ? $book->category->name : null,
                ],
                'history' => $history,
                'stats' => $book->getBorrowingStats(),
            ]
        ]);
    }

    /**
     * Get current borrowers of a book (public)
     */
    public function currentBorrowers($id)
    {
        $book = Book::with(['category'])->where('status', 1)->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        $currentBorrowers = $book->currentBorrowings()
            ->with('user')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function($borrowing) {
                return [
                    'borrowing_id' => $borrowing->id,
                    'borrow_code' => $borrowing->borrow_code,
                    'user' => [
                        'id' => $borrowing->user->id,
                        'name' => $borrowing->user->name,
                        'nim' => $borrowing->user->nim,
                        'email' => $borrowing->user->email,
                    ],
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'days_remaining' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) : null,
                    'is_overdue' => $borrowing->isOverdue(),
                    'is_extended' => $borrowing->is_extended,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Current borrowers retrieved successfully',
            'data' => [
                'book' => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author,
                    'available_stock' => $book->available_stock,
                    'total_stock' => $book->stock,
                ],
                'current_borrowers' => $currentBorrowers,
                'total_current_borrowers' => $currentBorrowers->count(),
            ]
        ]);
    }

    /**
     * Get popular books based on borrowings (public)
     */
    public function popularBooks(Request $request)
    {
        $query = Book::with(['category'])
            ->where('status', 1)
            ->withCount(['borrowings as total_borrowings'])
            ->withCount(['borrowings as recent_borrowings' => function($q) {
                $q->where('created_at', '>=', now()->subMonths(3));
            }])
            ->orderBy('total_borrowings', 'desc')
            ->orderBy('recent_borrowings', 'desc');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('limit')) {
            $limit = $request->limit;
        } else {
            $limit = 10;
        }

        $books = $query->limit($limit)->get();

        $formattedBooks = $books->map(function($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'cover_image_url' => $book->cover_image_url,
                'category' => $book->category ? $book->category->name : null,
                'total_borrowings' => $book->total_borrowings,
                'recent_borrowings' => $book->recent_borrowings,
                'popularity_score' => ($book->total_borrowings * 1) + ($book->recent_borrowings * 2),
                'available_stock' => $book->available_stock,
                'status' => $book->status_text,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Popular books retrieved successfully',
            'data' => $formattedBooks,
            'stats' => [
                'total_books' => $books->count(),
                'most_popular' => $books->first() ? [
                    'title' => $books->first()->title,
                    'total_borrowings' => $books->first()->total_borrowings,
                ] : null,
            ]
        ]);
    }

    /**
     * Search books with borrowing info (public)
     */
    public function searchWithBorrowingInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $books = Book::with(['category'])
            ->where('status', 1)
            ->withCount('borrowings')
            ->where(function($query) use ($request) {
                $query->where('title', 'like', "%{$request->search}%")
                      ->orWhere('author', 'like', "%{$request->search}%")
                      ->orWhere('isbn', 'like', "%{$request->search}%");
            })
            ->orderBy('borrowings_count', 'desc')
            ->paginate(15);

        $formattedBooks = $books->getCollection()->map(function($book) {
            return $book->toApiResponse(false);
        });

        $books->setCollection($formattedBooks);

        return response()->json([
            'success' => true,
            'message' => 'Books with borrowing info retrieved successfully',
            'data' => $books
        ]);
    }

    /**
     * Download ebook (authenticated users only)
     */
    public function downloadEbook($id)
    {
        $user = auth()->user();
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        if (!$book->isSoftCopy()) {
            return response()->json([
                'success' => false,
                'message' => 'This book is not available in digital format'
            ], 400);
        }

        if (!$book->file_path || !Storage::exists($book->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Ebook file not found'
            ], 404);
        }

        // Check if user has permission to download
        // Anda bisa tambahkan logika khusus di sini

        // Log download activity
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'download_ebook',
            'description' => "Downloaded ebook: {$book->title}",
            'ip_address' => request()->ip(),
        ]);

        return Storage::download($book->file_path, $book->title . '.pdf');
    }

    // ==================== ADMIN METHODS ====================

    /**
     * Get all books (admin)
     */
    public function index(Request $request)
    {
        $query = Book::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 15);

        $books = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully',
            'data' => $books
        ]);
    }

    /**
     * Create new book (admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'isbn' => 'required|string|max:20|unique:books,isbn',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'stock' => 'required|integer|min:0',
            'available_stock' => 'required|integer|min:0|lte:stock',
            'book_type' => 'required|in:hardcopy,softcopy',
            'description' => 'nullable|string',
            'synopsis' => 'nullable|string',
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $bookData = $request->only([
            'isbn', 'title', 'category_id', 'author', 'publisher',
            'publication_year', 'stock', 'available_stock', 'book_type',
            'description', 'synopsis', 'pages', 'language', 'status'
        ]);

        // Generate slug
        $bookData['slug'] = \Str::slug($request->title);

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $validator = Validator::make($request->all(), [
                'cover_image' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cover image validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $path = $request->file('cover_image')->store('books/covers', 'public');
            $bookData['cover_image'] = $path;
        }

        // Handle ebook file upload (for softcopy)
        if ($request->hasFile('file_path') && $request->book_type === 'softcopy') {
            $validator = Validator::make($request->all(), [
                'file_path' => 'file|mimes:pdf,epub,mobi|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ebook file validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $path = $request->file('file_path')->store('books/ebooks', 'public');
            $bookData['file_path'] = $path;
        }

        $book = Book::create($bookData);

        return response()->json([
            'success' => true,
            'message' => 'Book created successfully',
            'data' => $book->load('category')
        ], 201);
    }

    /**
     * Get single book (admin)
     */
    public function show($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Book retrieved successfully',
            'data' => $book
        ]);
    }

    /**
     * Get book with detailed borrowing info (admin)
     */
    public function showWithBorrowingInfo($id)
    {
        $book = Book::with(['category', 'borrowings.user'])->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        // Get recent borrowings (last 10)
        $recentBorrowings = $book->borrowings()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'borrow_code' => $borrowing->borrow_code,
                    'user_name' => $borrowing->user->name,
                    'user_nim' => $borrowing->user->nim,
                    'status' => $borrowing->status,
                    'borrow_date' => $borrowing->borrow_date ? $borrowing->borrow_date->format('d-m-Y') : null,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'return_date' => $borrowing->return_date ? $borrowing->return_date->format('d-m-Y') : null,
                    'is_late' => $borrowing->isOverdue(),
                ];
            });

        // Get current active borrowings
        $activeBorrowings = $book->currentBorrowings()
            ->with('user')
            ->get()
            ->map(function($borrowing) {
                return [
                    'id' => $borrowing->id,
                    'borrow_code' => $borrowing->borrow_code,
                    'user_name' => $borrowing->user->name,
                    'user_nim' => $borrowing->user->nim,
                    'status' => $borrowing->status,
                    'due_date' => $borrowing->due_date ? $borrowing->due_date->format('d-m-Y') : null,
                    'days_remaining' => $borrowing->due_date ? now()->diffInDays($borrowing->due_date, false) : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Book with borrowing info retrieved successfully',
            'data' => [
                'book' => $book->toApiResponse(true),
                'borrowing_info' => [
                    'stats' => $book->getBorrowingStats(),
                    'recent_borrowings' => $recentBorrowings,
                    'active_borrowings' => $activeBorrowings,
                    'active_borrowers_count' => $activeBorrowings->count(),
                ]
            ]
        ]);
    }

    /**
     * Update book (admin)
     */
    public function update(Request $request, $id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'isbn' => 'required|string|max:20|unique:books,isbn,' . $id,
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'stock' => 'required|integer|min:0',
            'available_stock' => 'required|integer|min:0|lte:stock',
            'book_type' => 'required|in:hardcopy,softcopy',
            'description' => 'nullable|string',
            'synopsis' => 'nullable|string',
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'status' => 'required|in:0,1',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'file_path' => 'nullable|file|mimes:pdf,epub,mobi|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $bookData = $request->only([
            'isbn', 'title', 'category_id', 'author', 'publisher',
            'publication_year', 'stock', 'available_stock', 'book_type',
            'description', 'synopsis', 'pages', 'language', 'status'
        ]);

        $bookData['slug'] = \Str::slug($request->title);

        // Handle cover image update
        if ($request->hasFile('cover_image')) {
            // Delete old cover image if exists
            if ($book->cover_image && Storage::exists($book->cover_image)) {
                Storage::delete($book->cover_image);
            }

            $path = $request->file('cover_image')->store('books/covers', 'public');
            $bookData['cover_image'] = $path;
        }

        // Handle ebook file update (for softcopy)
        if ($request->hasFile('file_path') && $request->book_type === 'softcopy') {
            // Delete old ebook file if exists
            if ($book->file_path && Storage::exists($book->file_path)) {
                Storage::delete($book->file_path);
            }

            $path = $request->file('file_path')->store('books/ebooks', 'public');
            $bookData['file_path'] = $path;
        } elseif ($request->book_type === 'hardcopy' && $book->file_path) {
            // If changing from softcopy to hardcopy, delete the file
            if (Storage::exists($book->file_path)) {
                Storage::delete($book->file_path);
            }
            $bookData['file_path'] = null;
        }

        $book->update($bookData);

        return response()->json([
            'success' => true,
            'message' => 'Book updated successfully',
            'data' => $book->load('category')
        ]);
    }

    /**
     * Delete book (admin)
     */
    public function destroy($id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        // Check if book has active borrowings
        if ($book->currentBorrowings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete book with active borrowings'
            ], 400);
        }

        // Delete cover image if exists
        if ($book->cover_image && Storage::exists($book->cover_image)) {
            Storage::delete($book->cover_image);
        }

        // Delete ebook file if exists
        if ($book->file_path && Storage::exists($book->file_path)) {
            Storage::delete($book->file_path);
        }

        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'Book deleted successfully'
        ]);
    }

    /**
     * Toggle book status (admin)
     */
    public function toggleStatus($id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        }

        $book->status = $book->status == 1 ? 0 : 1;
        $book->save();

        return response()->json([
            'success' => true,
            'message' => 'Book status updated successfully',
            'data' => [
                'id' => $book->id,
                'title' => $book->title,
                'status' => $book->status,
                'status_text' => $book->status_text
            ]
        ]);
    }

    /**
     * Get books by category with stats (admin)
     */
    public function booksByCategory($categoryId)
    {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $books = $category->books()
            ->withCount('borrowings')
            ->orderBy('borrowings_count', 'desc')
            ->paginate(15);

        $stats = [
            'total_books' => $books->total(),
            'active_books' => $books->where('status', 1)->count(),
            'total_borrowings' => $books->sum('borrowings_count'),
            'available_books' => $books->where('status', 1)->where('available_stock', '>', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Books by category retrieved successfully',
            'data' => [
                'category' => $category,
                'books' => $books,
                'stats' => $stats
            ]
        ]);
    }
}
