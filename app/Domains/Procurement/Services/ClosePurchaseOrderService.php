<?php

namespace App\Domains\Procurement\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClosePurchaseOrderService
{
    public function execute(int $tenantId, int $purchaseOrderId, int $closedByUserId, string $closeReason): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $purchaseOrderId, $closedByUserId, $closeReason): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->status === PurchaseOrderStatus::Cancelled) {
                throw new InvalidArgumentException(__('This purchase order is already closed.'));
            }

            if ($purchaseOrder->status === PurchaseOrderStatus::Received) {
                throw new InvalidArgumentException(__('Received purchase orders cannot be closed as cancelled.'));
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'closed_at' => now(),
                'closed_by' => $closedByUserId,
                'close_reason' => trim($closeReason),
            ]);

            return $purchaseOrder->fresh(['supplier', 'lines.product', 'goodsReceipts', 'closedByUser']);
        });
    }
}
