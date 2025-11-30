<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryMovement extends TenantModel
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'reference_id',
        'reference_type',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'reason',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    /**
     * Type constants.
     */
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Reference type constants.
     */
    public const REF_SALE = 'sale';
    public const REF_PURCHASE = 'purchase';
    public const REF_ADJUSTMENT = 'adjustment';
    public const REF_RETURN = 'return';

    /**
     * Get the product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who made the movement.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a movement for stock out (sale).
     */
    public static function stockOut(
        Product $product,
        int $quantity,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?User $user = null
    ): self {
        $quantityBefore = $product->stock_quantity;

        return self::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'type' => self::TYPE_OUT,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore - $quantity,
            'unit_cost' => $product->cost_price,
        ]);
    }

    /**
     * Create a movement for stock in (purchase/return).
     */
    public static function stockIn(
        Product $product,
        int $quantity,
        ?float $unitCost = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?User $user = null
    ): self {
        $quantityBefore = $product->stock_quantity;

        return self::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'type' => self::TYPE_IN,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore + $quantity,
            'unit_cost' => $unitCost ?? $product->cost_price,
        ]);
    }

    /**
     * Create a movement for stock adjustment.
     */
    public static function adjustment(
        Product $product,
        int $newQuantity,
        string $reason,
        ?string $notes = null,
        ?User $user = null
    ): self {
        $quantityBefore = $product->stock_quantity;
        $quantityDiff = $newQuantity - $quantityBefore;

        return self::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'reference_type' => self::REF_ADJUSTMENT,
            'type' => self::TYPE_ADJUSTMENT,
            'quantity' => abs($quantityDiff),
            'quantity_before' => $quantityBefore,
            'quantity_after' => $newQuantity,
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by product.
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
