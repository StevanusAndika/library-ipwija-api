<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // Public access
    public function indexPublic()
    {
        $categories = Category::where('status', 1) // GANTI
            ->where('can_borrow', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'description']);

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
    }

    // Admin only
    public function index(Request $request)
    {
        $query = Category::withCount(['books', 'books as available_books_count' => function($query) {
            $query->where('status', 1) // GANTI
                  ->where('available_stock', '>', 0);
        }]);

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by can_borrow
        if ($request->has('can_borrow')) {
            $query->where('can_borrow', $request->boolean('can_borrow'));
        }

        // Filter by active status - GANTI
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort options
        $sortField = $request->get('sort_field', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories',
            'type' => 'required|in:umum,penelitian,referensi,fiksi',
            'description' => 'nullable|string',
            'max_borrow_days' => 'required|integer|min:1|max:30',
            'can_borrow' => 'boolean',
            'status' => 'in:0,1' // GANTI
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Auto set can_borrow based on type
        $canBorrow = $request->type !== 'penelitian';

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(4),
            'type' => $request->type,
            'description' => $request->description,
            'max_borrow_days' => $request->max_borrow_days,
            'can_borrow' => $request->has('can_borrow') ? $request->boolean('can_borrow') : $canBorrow,
            'status' => $request->has('status') ? $request->status : 1 // GANTI
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = Category::with(['books' => function($query) {
            $query->where('status', 1) // GANTI
                  ->orderBy('title')
                  ->limit(10);
        }])->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'data' => null
            ], 404);
        }

        // Get statistics
        $stats = [
            'total_books' => $category->books()->count(),
            'active_books' => $category->books()->where('status', 1)->count(), // GANTI
            'available_books' => $category->books()->where('status', 1) // GANTI
                ->where('available_stock', '>', 0)->count(),
            'hardcopy_count' => $category->books()->where('book_type', 'hardcopy')->count(),
            'softcopy_count' => $category->books()->where('book_type', 'softcopy')->count()
        ];

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully',
            'data' => [
                'category' => $category,
                'statistics' => $stats
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'data' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'type' => 'required|in:umum,penelitian,referensi,fiksi',
            'description' => 'nullable|string',
            'max_borrow_days' => 'required|integer|min:1|max:30',
            'can_borrow' => 'boolean',
            'status' => 'in:0,1' // GANTI
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(4),
            'type' => $request->type,
            'description' => $request->description,
            'max_borrow_days' => $request->max_borrow_days,
            'can_borrow' => $request->boolean('can_borrow', $category->can_borrow),
            'status' => $request->has('status') ? $request->status : $category->status // GANTI
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'data' => null
            ], 404);
        }

        // Check if category has books
        if ($category->books()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category that has books'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    public function booksByCategory($id, Request $request)
    {
        $category = Category::find($id);

        if (!$category || $category->status != 1) { // GANTI
            return response()->json([
                'success' => false,
                'message' => 'Category not found or inactive',
                'data' => null
            ], 404);
        }

        $query = $category->books()
            ->where('status', 1) // GANTI
            ->where('available_stock', '>', 0);

        // Search within category
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('synopsis', 'like', "%{$search}%");
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

        // Sort options
        $sortField = $request->get('sort_field', 'title');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->get('per_page', 12);
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books in category retrieved successfully',
            'data' => [
                'category' => $category->only(['id', 'name', 'type', 'description']),
                'books' => $books
            ]
        ]);
    }
}
