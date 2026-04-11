<?php

namespace App\Domains\Inventory\Services;

use App\Models\InventoryMovement;

class GetProductOnHandQuantityService
{
    /**
     * On-hand quantity is the sum of signed movement quantities for the product in the tenant.
     */
    public function execute(int $tenantId, int $productId): string
    {
        $sum = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->sum('quantity');

        return $sum === null ? '0' : (string) $sum;
    }
}
