<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\DTOs\ProductStockKpis;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesShipmentStatus;
use App\Models\GoodsReceipt;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\SalesShipment;
use Carbon\Carbon;

class GetProductStockKpisService
{
    public function execute(int $tenantId, int $productId): ProductStockKpis
    {
        $onHand = (string) InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->sum('quantity');

        $incoming = $this->incomingOpenPurchaseQuantity($tenantId, $productId);
        $committed = $this->committedOpenSalesQuantity($tenantId, $productId);

        $lastMovement = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $lastReceiptRaw = GoodsReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('status', GoodsReceiptStatus::Posted)
            ->whereHas('lines.purchaseOrderLine', fn ($q) => $q->where('product_id', $productId))
            ->max('received_at');

        $lastShipmentRaw = SalesShipment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', SalesShipmentStatus::Posted)
            ->whereHas('lines.salesOrderLine', fn ($q) => $q->where('product_id', $productId))
            ->max('shipped_at');

        return new ProductStockKpis(
            onHand: $onHand,
            incoming: $incoming,
            committed: $committed,
            lastMovementAtIso: $lastMovement?->created_at?->toIso8601String(),
            lastReceiptAtIso: $this->toIso8601($lastReceiptRaw),
            lastShipmentAtIso: $this->toIso8601($lastShipmentRaw),
        );
    }

    private function incomingOpenPurchaseQuantity(int $tenantId, int $productId): string
    {
        $sum = PurchaseOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', '!=', PurchaseOrderStatus::Cancelled))
            ->with(['goodsReceiptLines' => fn ($q) => $q->whereHas('goodsReceipt', fn ($gq) => $gq->where('status', GoodsReceiptStatus::Posted))])
            ->get()
            ->sum(function (PurchaseOrderLine $line): float {
                $received = (float) $line->goodsReceiptLines->sum('quantity_received');

                return max(0, (float) $line->quantity_ordered - $received);
            });

        return $this->formatDecimal($sum);
    }

    private function committedOpenSalesQuantity(int $tenantId, int $productId): string
    {
        $sum = SalesOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('salesOrder', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', '!=', SalesOrderStatus::Cancelled))
            ->with(['shipmentLines' => fn ($q) => $q->whereHas('salesShipment', fn ($sq) => $sq->where('status', SalesShipmentStatus::Posted))])
            ->get()
            ->sum(function (SalesOrderLine $line): float {
                $shipped = (float) $line->shipmentLines->sum('quantity_shipped');

                return max(0, (float) $line->quantity_ordered - $shipped);
            });

        return $this->formatDecimal($sum);
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    private function toIso8601(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
