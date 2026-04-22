<?php

namespace App\Domains\Inventory\Services;

use App\Enums\InventoryMovementType;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryMovement;
use App\Models\SalesShipmentLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ListInventoryMovementsForTenantService
{
    /**
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     movement_type?: string|null,
     *     product_search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     product_id?: int|null
     * }  $filters
     */
    public function query(int $tenantId, array $filters = []): Builder
    {
        $q = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'product',
                'reference' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        GoodsReceiptLine::class => ['goodsReceipt.purchaseOrder'],
                        SalesShipmentLine::class => ['salesShipment.salesOrder', 'salesOrderLine'],
                    ]);
                },
            ]);

        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : 0;
        if ($productId > 0) {
            $q->where('inventory_movements.product_id', $productId);
        }

        $dateFrom = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
        if ($dateFrom !== '') {
            $q->whereDate('inventory_movements.created_at', '>=', $dateFrom);
        }

        $dateTo = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';
        if ($dateTo !== '') {
            $q->whereDate('inventory_movements.created_at', '<=', $dateTo);
        }

        $type = isset($filters['movement_type']) ? trim((string) $filters['movement_type']) : '';
        if ($type !== '') {
            $cases = array_column(InventoryMovementType::cases(), 'value');
            if (in_array($type, $cases, true)) {
                $q->where('inventory_movements.movement_type', $type);
            }
        }

        $search = isset($filters['product_search']) ? trim((string) $filters['product_search']) : '';
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->whereHas('product', function (Builder $pq) use ($like): void {
                $pq->where('products.name', 'like', $like)
                    ->orWhere('products.sku', 'like', $like);
            });
        }

        $sort = isset($filters['sort']) ? trim((string) $filters['sort']) : 'created_at';
        $direction = isset($filters['direction']) ? strtolower(trim((string) $filters['direction'])) : 'desc';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return match ($sort) {
            'quantity' => $q->orderBy('inventory_movements.quantity', $direction)
                ->orderBy('inventory_movements.id', 'desc'),
            'product' => $q->join('products', 'products.id', '=', 'inventory_movements.product_id')
                ->orderBy('products.name', $direction)
                ->orderBy('inventory_movements.id', 'desc')
                ->select('inventory_movements.*'),
            default => $q->orderBy('inventory_movements.created_at', $direction)
                ->orderBy('inventory_movements.id', 'desc'),
        };
    }
}
