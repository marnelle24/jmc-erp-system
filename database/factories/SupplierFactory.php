<?php

namespace Database\Factories;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'code' => null,
            'status' => SupplierStatus::Active,
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'payment_terms' => fake()->optional()->randomElement(['Net 15', 'Net 30', 'Net 60', 'COD']),
            'tax_id' => null,
            'notes' => null,
        ];
    }
}
