<?php

namespace Database\Factories\Central;

use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name).'-'.Str::random(5);

        return [
            'name' => $name,
            'slug' => $slug,
            'domain' => $slug.'.posapp.com',
            'database' => config('database.tenant_prefix', 'tenant_').$slug,
            'email' => fake()->unique()->companyEmail(),
            'status' => Tenant::STATUS_PENDING,
            'plan' => Tenant::PLAN_FREE,
            'settings' => [
                'timezone' => 'UTC',
                'currency' => 'USD',
                'date_format' => 'Y-m-d',
            ],
            'trial_ends_at' => now()->addDays(14),
            'activated_at' => null,
        ];
    }

    /**
     * Indicate that the tenant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_SUSPENDED,
        ]);
    }

    /**
     * Indicate that the tenant is on the basic plan.
     */
    public function basicPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => Tenant::PLAN_BASIC,
        ]);
    }

    /**
     * Indicate that the tenant is on the professional plan.
     */
    public function professionalPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => Tenant::PLAN_PROFESSIONAL,
        ]);
    }

    /**
     * Indicate that the tenant is on the enterprise plan.
     */
    public function enterprisePlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => Tenant::PLAN_ENTERPRISE,
        ]);
    }

    /**
     * Indicate that the tenant's trial has expired.
     */
    public function trialExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDays(1),
        ]);
    }
}
