<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreatePurchaseOrderFromRfqService
{
    public function __construct(
        private readonly CreatePurchaseOrderService $createPurchaseOrder,
    ) {}

    public function execute(int $tenantId, int $rfqId): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $rfqId): PurchaseOrder {
            $rfq = Rfq::query()
                ->where('tenant_id', $tenantId)
                ->with('lines')
                ->whereKey($rfqId)
                ->firstOrFail();

            if ($rfq->status === RfqStatus::Closed || $rfq->purchaseOrders()->exists()) {
                throw new InvalidArgumentException(__('A purchase order has already been created from this RFQ.'));
            }

            if ($rfq->status === RfqStatus::PendingForApproval) {
                throw new InvalidArgumentException(__('Approve this RFQ before creating a purchase order.'));
            }

            if ($rfq->lines->isEmpty()) {
                throw new InvalidArgumentException(__('This request for quotation has no line items.'));
            }

            $lines = [];
            foreach ($rfq->lines as $line) {
                $lines[] = [
                    'product_id' => $line->product_id,
                    'quantity_ordered' => (string) $line->quantity,
                    'unit_cost' => $line->unit_price !== null ? (string) $line->unit_price : null,
                    'rfq_line_id' => $line->id,
                ];
            }

            $po = $this->createPurchaseOrder->execute($tenantId, [
                'supplier_id' => $rfq->supplier_id,
                'rfq_id' => $rfq->id,
                'order_date' => now()->toDateString(),
                'notes' => $rfq->notes,
                'lines' => $lines,
            ]);

            $rfq->update(['status' => RfqStatus::Closed]);

            return $po->load(['lines', 'supplier']);
        });
    }
}
