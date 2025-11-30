<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Sale;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);
        $taxAmount = $subtotal * 0.1;
        $total = $subtotal + $taxAmount;

        return [
            'invoice_number' => Sale::generateInvoiceNumber(),
            'customer_id' => null,
            'user_id' => User::factory(),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => 0,
            'discount_type' => null,
            'total' => $total,
            'amount_paid' => $total,
            'change_amount' => 0,
            'payment_method' => Sale::PAYMENT_CASH,
            'status' => Sale::STATUS_COMPLETED,
            'notes' => null,
            'payment_details' => null,
            'completed_at' => now(),
        ];
    }

    /**
     * Indicate that the sale is pending.
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Sale::STATUS_PENDING,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the sale is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Sale::STATUS_CANCELLED,
        ]);
    }

    /**
     * Indicate that the sale has a customer.
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn(array $attributes) => [
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Indicate that the sale was made by a specific user.
     */
    public function byCashier(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate the payment method.
     */
    public function paymentMethod(string $method): static
    {
        return $this->state(fn(array $attributes) => [
            'payment_method' => $method,
        ]);
    }

    /**
     * Indicate that the sale has a discount.
     */
    public function withDiscount(float $amount, string $type = 'fixed'): static
    {
        return $this->state(function (array $attributes) use ($amount, $type) {
            $total = $attributes['subtotal'] + $attributes['tax_amount'] - $amount;
            return [
                'discount_amount' => $amount,
                'discount_type' => $type,
                'total' => max(0, $total),
                'amount_paid' => max(0, $total),
            ];
        });
    }
}
