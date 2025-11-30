<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->when($request->search, fn ($q, $search) => $q->search($search))
            ->when($request->category_id, fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->when($request->boolean('out_of_stock'), fn ($q) => $q->outOfStock())
            ->with('category')
            ->orderBy($request->sort_by ?? 'name', $request->sort_dir ?? 'asc');

        if ($request->boolean('paginate', true)) {
            $products = $query->paginate($request->per_page ?? 15);
        } else {
            $products = $query->get();
        }

        return response()->json($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenant.products,slug',
            'sku' => 'nullable|string|max:100|unique:tenant.products,sku',
            'barcode' => 'nullable|string|max:100|unique:tenant.products,barcode',
            'description' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_id' => 'nullable|uuid|exists:tenant.categories,id',
            'unit' => 'nullable|string|max:50',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'attributes' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'track_inventory' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::create($validator->validated());

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('category'),
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with('category', 'inventoryMovements')->find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:tenant.products,slug,'.$id,
            'sku' => 'sometimes|string|max:100|unique:tenant.products,sku,'.$id,
            'barcode' => 'nullable|string|max:100|unique:tenant.products,barcode,'.$id,
            'description' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_id' => 'nullable|uuid|exists:tenant.categories,id',
            'unit' => 'nullable|string|max:50',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'attributes' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'track_inventory' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product->update($validator->validated());

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('category'),
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        // Check if product has been sold
        if ($product->saleItems()->exists()) {
            // Soft delete instead
            $product->delete();

            return response()->json([
                'message' => 'Product archived successfully (has sales history)',
            ]);
        }

        // Force delete if no sales
        $product->forceDelete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Find product by barcode.
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        $product = Product::where('barcode', $barcode)->active()->first();

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product->load('category'));
    }

    /**
     * Find product by SKU.
     */
    public function findBySku(string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->active()->first();

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product->load('category'));
    }

    /**
     * Update product stock.
     */
    public function updateStock(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'type' => 'required|in:set,add,subtract',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldQuantity = $product->stock_quantity;
        $newQuantity = match ($request->type) {
            'set' => $request->quantity,
            'add' => $oldQuantity + $request->quantity,
            'subtract' => max(0, $oldQuantity - $request->quantity),
        };

        $product->update(['stock_quantity' => $newQuantity]);

        // Create inventory movement
        \App\Models\Tenant\InventoryMovement::adjustment(
            $product,
            $newQuantity,
            $request->reason,
            $request->notes,
            $request->user()
        );

        return response()->json([
            'message' => 'Stock updated successfully',
            'product' => $product->fresh(),
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
        ]);
    }

    /**
     * Get low stock products.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $products = Product::lowStock()
            ->active()
            ->with('category')
            ->orderBy('stock_quantity')
            ->paginate($request->per_page ?? 15);

        return response()->json($products);
    }
}
