<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;

class CreateProductService
{
    /**
     * @param  array{name: string, sku?: string|null, description?: string|null}  $data
     */
    public function execute(int $tenantId, array $data): Product
    {
        $sku = $data['sku'] ?? null;
        if ($sku === '') {
            $sku = null;
        }

        return Product::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'sku' => $sku,
            'description' => $data['description'] ?? null,
        ]);
    }
}
