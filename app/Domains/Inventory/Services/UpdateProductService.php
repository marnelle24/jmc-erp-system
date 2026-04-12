<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;

class UpdateProductService
{
    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function execute(Product $product, array $data): Product
    {
        $product->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $product->fresh();
    }
}
