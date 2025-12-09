<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookController extends Controller
{
    // Public access - tanpa login
    public function indexPublic(Request $request)
    {
        $query = Book::with('category')
            ->where('status', 1)
            ->where('available_stock', '>', 0);

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%")
                  ->orWhere('synopsis', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by book type
        if ($request->has('book_type')) {
            $query->where('book_type', $request->book_type);
        }

        // Filter by language
        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        // Sort options
        $sortField = $request->get('sort_field', 'title');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->get('per_page', 12);
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully',
            'data' => $books
        ]);
    }

    public function showPublic($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book || $book->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        // Hide file_path for public
        $book->makeHidden(['file_path']);

        return response()->json([
            'success' => true,
            'message' => 'Book retrieved successfully',
            'data' => $book
        ]);
    }

    // Admin only - semua buku
    public function index(Request $request)
    {
        $query = Book::with('category');

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by book type
        if ($request->has('book_type')) {
            $query->where('book_type', $request->book_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by availability
        if ($request->has('available')) {
            $query->where('available_stock', '>', 0);
        }

        // Sort options
        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully',
            'data' => $books
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'isbn' => 'nullable|string|max:20|unique:books',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'stock' => 'required|integer|min:1',
            'book_type' => 'required|in:hardcopy,softcopy',
            'file_path' => 'required_if:book_type,softcopy|nullable|file|mimes:pdf|max:10240',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description' => 'nullable|string',
            'synopsis' => 'nullable|string',
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check category - PERBAIKAN: tambahkan pengecekan null
        $category = Category::find($request->category_id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        if ($category->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Category is not active'
            ], 400);
        }

        $bookData = [
            'isbn' => $request->isbn,
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . Str::random(6),
            'category_id' => $request->category_id,
            'author' => $request->author,
            'publisher' => $request->publisher,
            'publication_year' => $request->publication_year,
            'stock' => $request->stock,
            'available_stock' => $request->stock,
            'book_type' => $request->book_type,
            'description' => $request->description,
            'synopsis' => $request->synopsis,
            'pages' => $request->pages,
            'language' => $request->language ?? 'Indonesia',
            'status' => $request->has('status') ? $request->status : 1
        ];

        // Handle softcopy file upload
        if ($request->book_type === 'softcopy' && $request->hasFile('file_path')) {
            $file = $request->file('file_path');
            $filename = 'ebook_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('ebooks', $filename, 'public');
            $bookData['file_path'] = $path;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $image = $request->file('cover_image');
            $imageName = 'cover_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('book_covers', $imageName, 'public');
            $bookData['cover_image'] = $imagePath;
        }

        $book = Book::create($bookData);

        return response()->json([
            'success' => true,
            'message' => 'Book created successfully',
            'data' => $book->load('category')
        ], 201);
    }

    public function show($id)
    {
        $book = Book::with(['category', 'borrowings' => function($query) {
            $query->whereIn('status', ['borrowed', 'late'])
                  ->with('user')
                  ->limit(5);
        }])->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Book retrieved successfully',
            'data' => $book
        ]);
    }

    public function update(Request $request, $id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'isbn' => 'nullable|string|max:20|unique:books,isbn,' . $id,
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'stock' => 'required|integer|min:0',
            'book_type' => 'required|in:hardcopy,softcopy',
            'file_path' => 'nullable|file|mimes:pdf|max:10240',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description' => 'nullable|string',
            'synopsis' => 'nullable|string',
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check category - PERBAIKAN: tambahkan pengecekan null
        $category = Category::find($request->category_id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        if ($category->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Category is not active'
            ], 400);
        }

        $bookData = [
            'isbn' => $request->isbn,
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . Str::random(6),
            'category_id' => $request->category_id,
            'author' => $request->author,
            'publisher' => $request->publisher,
            'publication_year' => $request->publication_year,
            'stock' => $request->stock,
            'book_type' => $request->book_type,
            'description' => $request->description,
            'synopsis' => $request->synopsis,
            'pages' => $request->pages,
            'language' => $request->language ?? $book->language,
            'status' => $request->has('status') ? $request->status : $book->status
        ];

        // Handle file update for softcopy
        if ($request->hasFile('file_path')) {
            // Delete old file if exists
            if ($book->file_path && Storage::disk('public')->exists($book->file_path)) {
                Storage::disk('public')->delete($book->file_path);
            }

            $file = $request->file('file_path');
            $filename = 'ebook_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('ebooks', $filename, 'public');
            $bookData['file_path'] = $path;
        }

        // Handle cover image update
        if ($request->hasFile('cover_image')) {
            // Delete old image if exists
            if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
                Storage::disk('public')->delete($book->cover_image);
            }

            $image = $request->file('cover_image');
            $imageName = 'cover_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('book_covers', $imageName, 'public');
            $bookData['cover_image'] = $imagePath;
        }

        // Calculate available stock
        $borrowedCount = $book->activeBorrowings()->count();
        $bookData['available_stock'] = max(0, $request->stock - $borrowedCount);

        $book->update($bookData);

        return response()->json([
            'success' => true,
            'message' => 'Book updated successfully',
            'data' => $book->load('category')
        ]);
    }

    public function destroy($id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        // Check if book has active borrowings
        if ($book->activeBorrowings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete book that has active borrowings'
            ], 400);
        }

        // Delete files
        if ($book->file_path && Storage::disk('public')->exists($book->file_path)) {
            Storage::disk('public')->delete($book->file_path);
        }

        if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'Book deleted successfully'
        ]);
    }

    public function downloadEbook($id)
    {
        $book = Book::find($id);

        if (!$book || $book->book_type !== 'softcopy' || !$book->file_path || $book->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Ebook not available'
            ], 404);
        }

        if (!Storage::disk('public')->exists($book->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $book->file_path);
        $fileName = Str::slug($book->title) . '.pdf';

        return response()->download($filePath, $fileName);
    }
}
