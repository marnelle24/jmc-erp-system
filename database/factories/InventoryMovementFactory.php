<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'movement_type' => InventoryMovementType::Receipt,
            'notes' => null,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }

    /**
     * @return $this
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'product_id' => $product->id,
            'tenant_id' => $product->tenant_id,
        ]);
    }
}
