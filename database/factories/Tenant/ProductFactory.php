<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $costPrice = fake()->randomFloat(2, 1, 100);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => strtoupper(Str::random(8)),
            'barcode' => fake()->ean13(),
            'description' => fake()->paragraph(),
            'cost_price' => $costPrice,
            'selling_price' => $costPrice * fake()->randomFloat(2, 1.2, 2.5),
            'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
            'stock_quantity' => fake()->numberBetween(0, 500),
            'low_stock_threshold' => 10,
            'category_id' => null,
            'unit' => fake()->randomElement(['piece', 'kg', 'liter', 'box']),
            'image' => null,
            'images' => null,
            'attributes' => null,
            'is_active' => true,
            'track_inventory' => true,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product has low stock.
     */
    public function lowStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'stock_quantity' => 5,
            'low_stock_threshold' => 10,
        ]);
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * Indicate that the product belongs to a category.
     */
    public function inCategory(Category $category): static
    {
        return $this->state(fn(array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    /**
     * Indicate that the product doesn't track inventory.
     */
    public function noInventoryTracking(): static
    {
        return $this->state(fn(array $attributes) => [
            'track_inventory' => false,
        ]);
    }
}
