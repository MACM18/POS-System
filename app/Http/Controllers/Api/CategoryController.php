<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()
            ->when($request->boolean('roots_only'), fn ($q) => $q->roots())
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->parent_id, fn ($q, $parentId) => $q->where('parent_id', $parentId))
            ->with('children')
            ->ordered();

        if ($request->boolean('paginate', true)) {
            $categories = $query->paginate($request->per_page ?? 15);
        } else {
            $categories = $query->get();
        }

        return response()->json($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenant.categories,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:tenant.categories,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = Category::create($validator->validated());

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category->load('parent', 'children'),
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::with('parent', 'children', 'products')->find($id);

        if (! $category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json($category);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:tenant.categories,slug,'.$id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:tenant.categories,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prevent setting self as parent
        if ($request->parent_id === $id) {
            return response()->json([
                'message' => 'Category cannot be its own parent',
            ], 422);
        }

        $category->update($validator->validated());

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh()->load('parent', 'children'),
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with products',
            ], 422);
        }

        // Move children to parent category
        if ($category->hasChildren()) {
            $category->children()->update(['parent_id' => $category->parent_id]);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Get category tree structure.
     */
    public function tree(): JsonResponse
    {
        $categories = Category::roots()
            ->active()
            ->ordered()
            ->with('descendants')
            ->get();

        return response()->json($categories);
    }
}
