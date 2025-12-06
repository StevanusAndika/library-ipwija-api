<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $query = Book::with('category')
            ->where('is_active', true);

        // Filter by search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%")
                  ->orWhere('publisher', 'like', "%{$search}%")
                  ->orWhere('synopsis', 'like', "%{$search}%"); // Include sinopsis in search
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by category type
        if ($request->has('type')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('type', $request->type);
            });
        }

        // Filter by book type
        if ($request->has('book_type')) {
            $query->where('book_type', $request->book_type);
        }

        // Filter by language
        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        // Filter by availability
        if ($request->has('available')) {
            $query->where('available_stock', '>', 0);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $books = $query->orderBy('title')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully',
            'data' => $books
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'isbn' => 'nullable|string|max:20|unique:books,isbn',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'stock' => 'required|integer|min:0',
            'book_type' => 'required|in:hardcopy,softcopy',
            'file_path' => 'required_if:book_type,softcopy|nullable|file|mimes:pdf,doc,docx,epub|max:10240',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description' => 'nullable|string',
            'synopsis' => 'nullable|string', // Validasi sinopsis
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if category can be borrowed
        $category = Category::find($request->category_id);
        if (!$category->can_borrow && $request->book_type === 'hardcopy') {
            return response()->json([
                'success' => false,
                'message' => 'Books in this category cannot be borrowed',
                'data' => null
            ], 400);
        }

        $bookData = [
            'isbn' => $request->isbn,
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . Str::random(5),
            'category_id' => $request->category_id,
            'author' => $request->author,
            'publisher' => $request->publisher,
            'publication_year' => $request->publication_year,
            'stock' => $request->stock,
            'available_stock' => $request->stock,
            'book_type' => $request->book_type,
            'description' => $request->description,
            'synopsis' => $request->synopsis, // Simpan sinopsis
            'pages' => $request->pages,
            'language' => $request->language ?? 'Indonesia',
        ];

        // Handle file upload for softcopy
        if ($request->book_type === 'softcopy' && $request->hasFile('file_path')) {
            $file = $request->file('file_path');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('ebooks', $filename, 'public');
            $bookData['file_path'] = $path;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $image = $request->file('cover_image');
            $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
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
        $book = Book::with('category')->find($id);

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
            'file_path' => 'nullable|file|mimes:pdf,doc,docx,epub|max:10240',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description' => 'nullable|string',
            'synopsis' => 'nullable|string', // Validasi sinopsis
            'pages' => 'nullable|integer|min:1',
            'language' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if category can be borrowed
        $category = Category::find($request->category_id);
        if (!$category->can_borrow && $request->book_type === 'hardcopy') {
            return response()->json([
                'success' => false,
                'message' => 'Books in this category cannot be borrowed',
                'data' => null
            ], 400);
        }

        $bookData = [
            'isbn' => $request->isbn,
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . Str::random(5),
            'category_id' => $request->category_id,
            'author' => $request->author,
            'publisher' => $request->publisher,
            'publication_year' => $request->publication_year,
            'stock' => $request->stock,
            'book_type' => $request->book_type,
            'description' => $request->description,
            'synopsis' => $request->synopsis, // Update sinopsis
            'pages' => $request->pages,
            'language' => $request->language ?? $book->language,
            'is_active' => $request->boolean('is_active', $book->is_active),
        ];

        // Handle file upload for softcopy
        if ($request->hasFile('file_path')) {
            // Delete old file if exists
            if ($book->file_path && Storage::disk('public')->exists($book->file_path)) {
                Storage::disk('public')->delete($book->file_path);
            }

            $file = $request->file('file_path');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('ebooks', $filename, 'public');
            $bookData['file_path'] = $path;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            // Delete old image if exists
            if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
                Storage::disk('public')->delete($book->cover_image);
            }

            $image = $request->file('cover_image');
            $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('book_covers', $imageName, 'public');
            $bookData['cover_image'] = $imagePath;
        }

        // Calculate new available stock
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
                'message' => 'Cannot delete book that has active borrowings',
                'data' => null
            ], 400);
        }

        // Delete associated files
        if ($book->file_path && Storage::disk('public')->exists($book->file_path)) {
            Storage::disk('public')->delete($book->file_path);
        }

        if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'Book deleted successfully',
            'data' => null
        ]);
    }

    public function downloadEbook($id)
    {
        $book = Book::find($id);

        if (!$book || $book->book_type !== 'softcopy' || !$book->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Ebook not available',
                'data' => null
            ], 404);
        }

        if (!Storage::disk('public')->exists($book->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
                'data' => null
            ], 404);
        }

        $downloadUrl = Storage::disk('public')->url($book->file_path);

        return response()->json([
            'success' => true,
            'message' => 'Download link generated',
            'data' => [
                'download_url' => $downloadUrl,
                'filename' => basename($book->file_path)
            ]
        ]);
    }

    // Get books with synopsis (public endpoint)
    public function getBooksWithSynopsis(Request $request)
    {
        $query = Book::with('category')
            ->where('is_active', true)
            ->whereNotNull('synopsis') // Only books with synopsis
            ->where('synopsis', '!=', '');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('synopsis', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $books = $query->orderBy('title')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books with synopsis retrieved successfully',
            'data' => $books
        ]);
    }
}
