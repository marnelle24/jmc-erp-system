<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;

class UpdateProductService
{
    /**
     * @param  array{name: string, description?: string|null, reorder_point?: string|null, reorder_qty?: string|null}  $data
     */
    public function execute(Product $product, array $data): Product
    {
        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ];

        if (array_key_exists('reorder_point', $data)) {
            $payload['reorder_point'] = $data['reorder_point'];
        }

        if (array_key_exists('reorder_qty', $data)) {
            $payload['reorder_qty'] = $data['reorder_qty'];
        }

        $product->update($payload);

        return $product->fresh();
    }
}
