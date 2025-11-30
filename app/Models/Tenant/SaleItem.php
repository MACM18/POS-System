<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleItem extends TenantModel
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'cost_price',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'subtotal',
        'total',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the sale that owns the item.
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the item totals.
     */
    public function calculateTotals(): array
    {
        $subtotal = $this->quantity * $this->unit_price;
        $taxAmount = $subtotal * ($this->tax_rate / 100);
        $total = $subtotal + $taxAmount - $this->discount_amount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => max(0, $total),
        ];
    }

    /**
     * Get profit for this item.
     */
    public function getProfitAttribute(): float
    {
        return ($this->unit_price - $this->cost_price) * $this->quantity;
    }

    /**
     * Create a sale item from a product.
     */
    public static function fromProduct(Product $product, int $quantity, float $discount = 0): array
    {
        $subtotal = $quantity * $product->selling_price;
        $taxAmount = $subtotal * ($product->tax_rate / 100);
        $total = $subtotal + $taxAmount - $discount;

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $product->selling_price,
            'cost_price' => $product->cost_price,
            'tax_rate' => $product->tax_rate,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discount,
            'subtotal' => $subtotal,
            'total' => max(0, $total),
        ];
    }
}
