<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    /**
     * Display a listing of sales.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->payment_method, fn($q, $method) => $q->paymentMethod($method))
            ->when($request->customer_id, fn($q, $customerId) => $q->where('customer_id', $customerId))
            ->when($request->user_id, fn($q, $userId) => $q->where('user_id', $userId))
            ->when(
                $request->start_date && $request->end_date,
                fn($q) => $q->dateRange($request->start_date, $request->end_date)
            )
            ->when($request->boolean('today'), fn($q) => $q->today())
            ->with(['customer', 'user', 'items.product'])
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        if ($request->boolean('paginate', true)) {
            $sales = $query->paginate($request->per_page ?? 15);
        } else {
            $sales = $query->get();
        }

        return response()->json($sales);
    }

    /**
     * Store a newly created sale.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|uuid|exists:tenant.customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'payment_method' => 'required|in:cash,card,mobile,credit,mixed',
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_details' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate stock availability
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                return response()->json([
                    'message' => "Product {$item['product_id']} not found",
                ], 404);
            }
            if (!$product->hasStock($item['quantity'])) {
                return response()->json([
                    'message' => "Insufficient stock for {$product->name}",
                    'available' => $product->stock_quantity,
                    'requested' => $item['quantity'],
                ], 422);
            }
        }

        try {
            DB::connection('tenant')->beginTransaction();

            // Create sale
            $sale = Sale::create([
                'customer_id' => $request->customer_id,
                'user_id' => $request->user()->id,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'discount_type' => $request->discount_type,
                'total' => 0,
                'amount_paid' => $request->amount_paid ?? 0,
                'payment_method' => $request->payment_method,
                'payment_details' => $request->payment_details,
                'notes' => $request->notes,
                'status' => Sale::STATUS_PENDING,
            ]);

            // Create sale items
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $discount = $itemData['discount'] ?? 0;

                $saleItemData = SaleItem::fromProduct($product, $itemData['quantity'], $discount);
                $saleItemData['sale_id'] = $sale->id;

                SaleItem::create($saleItemData);

                $subtotal += $saleItemData['subtotal'];
                $taxAmount += $saleItemData['tax_amount'];

                // Decrement stock
                $product->decrementStock($itemData['quantity']);

                // Create inventory movement
                InventoryMovement::stockOut(
                    $product,
                    $itemData['quantity'],
                    $sale->id,
                    InventoryMovement::REF_SALE,
                    $request->user()
                );
            }

            // Calculate totals
            $total = $subtotal + $taxAmount - ($request->discount_amount ?? 0);
            $changeAmount = max(0, ($request->amount_paid ?? 0) - $total);

            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'change_amount' => $changeAmount,
            ]);

            // Complete the sale if fully paid
            if (($request->amount_paid ?? 0) >= $total) {
                $sale->complete();
            }

            DB::connection('tenant')->commit();

            return response()->json([
                'message' => 'Sale created successfully',
                'sale' => $sale->fresh()->load(['customer', 'user', 'items.product']),
            ], 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();

            return response()->json([
                'message' => 'Failed to create sale',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified sale.
     */
    public function show(string $id): JsonResponse
    {
        $sale = Sale::with(['customer', 'user', 'items.product'])->find($id);

        if (!$sale) {
            return response()->json([
                'message' => 'Sale not found',
            ], 404);
        }

        return response()->json($sale);
    }

    /**
     * Cancel a sale.
     */
    public function cancel(string $id): JsonResponse
    {
        $sale = Sale::with('items.product')->find($id);

        if (!$sale) {
            return response()->json([
                'message' => 'Sale not found',
            ], 404);
        }

        if (!$sale->canBeCancelled()) {
            return response()->json([
                'message' => 'Sale cannot be cancelled',
                'status' => $sale->status,
            ], 422);
        }

        try {
            DB::connection('tenant')->beginTransaction();

            // Restore stock for each item
            foreach ($sale->items as $item) {
                $item->product->incrementStock($item->quantity);

                // Create inventory movement for return
                InventoryMovement::stockIn(
                    $item->product,
                    $item->quantity,
                    $item->cost_price,
                    $sale->id,
                    InventoryMovement::REF_RETURN,
                    request()->user()
                );
            }

            $sale->update(['status' => Sale::STATUS_CANCELLED]);

            DB::connection('tenant')->commit();

            return response()->json([
                'message' => 'Sale cancelled successfully',
                'sale' => $sale->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();

            return response()->json([
                'message' => 'Failed to cancel sale',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sale statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->start_date ?? today()->startOfMonth();
        $endDate = $request->end_date ?? today()->endOfDay();

        $stats = [
            'total_sales' => Sale::completed()
                ->dateRange($startDate, $endDate)
                ->sum('total'),
            'total_transactions' => Sale::completed()
                ->dateRange($startDate, $endDate)
                ->count(),
            'average_sale' => Sale::completed()
                ->dateRange($startDate, $endDate)
                ->avg('total') ?? 0,
            'total_tax' => Sale::completed()
                ->dateRange($startDate, $endDate)
                ->sum('tax_amount'),
            'total_discount' => Sale::completed()
                ->dateRange($startDate, $endDate)
                ->sum('discount_amount'),
            'by_payment_method' => Sale::completed()
                ->dateRange($startDate, $endDate)
                ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
                ->groupBy('payment_method')
                ->get(),
            'today' => [
                'total' => Sale::completed()->today()->sum('total'),
                'count' => Sale::completed()->today()->count(),
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Get daily sales report.
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $startDate = $request->start_date ?? today()->subDays(30);
        $endDate = $request->end_date ?? today();

        $report = Sale::completed()
            ->dateRange($startDate, $endDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($report);
    }
}
