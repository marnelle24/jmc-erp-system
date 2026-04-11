<?php

namespace App\Domains\Inventory\Services;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostInventoryMovementService
{
    /**
     * Stock changes are recorded only as movement rows; do not update product columns for quantity.
     *
     * @throws InvalidArgumentException If the product does not belong to the tenant
     */
    public function execute(
        int $tenantId,
        int $productId,
        string $quantity,
        InventoryMovementType $type,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $productId, $quantity, $type, $notes, $referenceType, $referenceId): InventoryMovement {
            $belongs = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($productId)
                ->exists();

            if (! $belongs) {
                throw new InvalidArgumentException(__('The selected product is not valid for this organization.'));
            }

            return InventoryMovement::query()->create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'movement_type' => $type,
                'notes' => $notes,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }
}
