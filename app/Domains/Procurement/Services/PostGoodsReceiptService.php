<?php

namespace App\Domains\Procurement\Services;

use App\Domains\Inventory\Services\PostInventoryMovementService;
use App\Enums\GoodsReceiptStatus;
use App\Enums\InventoryMovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RfqLine;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostGoodsReceiptService
{
    public function __construct(
        private readonly PostInventoryMovementService $postMovement,
    ) {}

    /**
     * @param  list<array{purchase_order_line_id: int, quantity_received: string, unit_cost?: string|null}>  $lines
     */
    public function execute(
        int $tenantId,
        int $purchaseOrderId,
        array $lines,
        CarbonInterface|string $receivedAt,
        ?string $supplierInvoiceReference = null,
        ?string $notes = null,
    ): GoodsReceipt {
        return DB::transaction(function () use ($tenantId, $purchaseOrderId, $lines, $receivedAt, $supplierInvoiceReference, $notes): GoodsReceipt {
            $purchaseOrder = PurchaseOrder::query()
                ->where('tenant_id', $tenantId)
                ->with(['lines.product'])
                ->whereKey($purchaseOrderId)
                ->firstOrFail();

            if ($purchaseOrder->status === PurchaseOrderStatus::Cancelled) {
                throw new InvalidArgumentException(__('Cannot receive against a cancelled purchase order.'));
            }

            $normalized = $this->normalizeLines($purchaseOrder, $lines);
            if ($normalized === []) {
                throw new InvalidArgumentException(__('Enter at least one positive quantity to receive.'));
            }

            $this->assertLinesBelongToOrder($purchaseOrder, array_keys($normalized));

            $receivedAtCarbon = $receivedAt instanceof CarbonInterface
                ? $receivedAt
                : Carbon::parse((string) $receivedAt);

            $goodsReceipt = GoodsReceipt::query()->create([
                'tenant_id' => $tenantId,
                'purchase_order_id' => $purchaseOrder->id,
                'status' => GoodsReceiptStatus::Posted,
                'received_at' => $receivedAtCarbon->toDateTimeString(),
                'supplier_invoice_reference' => $supplierInvoiceReference,
                'notes' => $notes,
            ]);

            foreach ($normalized as $purchaseOrderLineId => $row) {
                $quantityReceived = $row['quantity'];
                $unitCostForLine = $row['unit_cost'];

                $poLine = $purchaseOrder->lines->firstWhere('id', $purchaseOrderLineId);
                if (! $poLine instanceof PurchaseOrderLine) {
                    continue;
                }

                $already = $this->sumReceivedQuantityForPurchaseOrderLine($purchaseOrderLineId);
                $ordered = (string) $poLine->quantity_ordered;
                $nextTotal = bcadd($already, $quantityReceived, 4);

                if (bccomp($nextTotal, $ordered, 4) === 1) {
                    throw new InvalidArgumentException(
                        __('Cannot receive more than ordered for :product.', ['product' => $poLine->product?->name ?? '#'.$poLine->product_id])
                    );
                }

                $receiptLine = GoodsReceiptLine::query()->create([
                    'goods_receipt_id' => $goodsReceipt->id,
                    'purchase_order_line_id' => $purchaseOrderLineId,
                    'quantity_received' => $quantityReceived,
                    'unit_cost' => $unitCostForLine,
                ]);

                $receiptLine->load('purchaseOrderLine.product');

                $this->postMovement->execute(
                    $tenantId,
                    $poLine->product_id,
                    $quantityReceived,
                    InventoryMovementType::Receipt,
                    __('Purchase receipt #:receipt (PO #:po)', [
                        'receipt' => $goodsReceipt->id,
                        'po' => $purchaseOrder->id,
                    ]),
                    GoodsReceiptLine::class,
                    $receiptLine->id,
                );
            }

            foreach (array_keys($normalized) as $purchaseOrderLineId) {
                $this->recalculatePurchaseOrderLineUnitCost((int) $purchaseOrderLineId);
            }

            $purchaseOrder->refresh()->load('lines');
            $purchaseOrder->update([
                'status' => $this->resolvePurchaseOrderStatus($purchaseOrder),
            ]);

            $normalizedQtyByLine = array_map(
                static fn (array $r): string => $r['quantity'],
                $normalized,
            );
            $this->syncRfqLineUnitPricesFromPurchaseOrder($purchaseOrder, $normalizedQtyByLine);

            return $goodsReceipt->load('lines');
        });
    }

    /**
     * @param  list<array{purchase_order_line_id: int, quantity_received: string, unit_cost?: string|null}>  $lines
     * @return array<int, array{quantity: string, unit_cost: string}>
     */
    private function normalizeLines(PurchaseOrder $purchaseOrder, array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            $qty = isset($row['quantity_received']) ? (string) $row['quantity_received'] : '0';
            if (bccomp($qty, '0', 4) !== 1) {
                continue;
            }
            $lineId = (int) $row['purchase_order_line_id'];
            if ($lineId < 1) {
                continue;
            }
            $poLine = $purchaseOrder->lines->firstWhere('id', $lineId);
            if (! $poLine instanceof PurchaseOrderLine) {
                continue;
            }

            $rawPrice = $row['unit_cost'] ?? '';
            $unitCost = ($rawPrice !== null && $rawPrice !== '')
                ? (string) $rawPrice
                : (string) ($poLine->unit_cost ?? '0');
            if (bccomp($unitCost, '0', 4) === -1) {
                $unitCost = '0';
            }

            if (isset($out[$lineId])) {
                $mergedQty = bcadd($out[$lineId]['quantity'], $qty, 4);
                $out[$lineId] = ['quantity' => $mergedQty, 'unit_cost' => $unitCost];
            } else {
                $out[$lineId] = ['quantity' => $qty, 'unit_cost' => $unitCost];
            }
        }

        return $out;
    }

    private function recalculatePurchaseOrderLineUnitCost(int $purchaseOrderLineId): void
    {
        $poLine = PurchaseOrderLine::query()->whereKey($purchaseOrderLineId)->first();
        if ($poLine === null) {
            return;
        }

        $grLines = GoodsReceiptLine::query()
            ->where('purchase_order_line_id', $purchaseOrderLineId)
            ->whereHas('goodsReceipt', fn ($q) => $q->where('status', GoodsReceiptStatus::Posted))
            ->get();

        $totalQty = '0';
        $totalCost = '0';
        foreach ($grLines as $grl) {
            $q = (string) $grl->quantity_received;
            $uc = $grl->unit_cost !== null
                ? (string) $grl->unit_cost
                : (string) ($poLine->unit_cost ?? '0');
            $totalQty = bcadd($totalQty, $q, 4);
            $totalCost = bcadd($totalCost, bcmul($q, $uc, 4), 4);
        }

        if (bccomp($totalQty, '0', 4) !== 1) {
            return;
        }

        $avg = bcdiv($totalCost, $totalQty, 4);
        $poLine->update(['unit_cost' => $avg]);
    }

    /**
     * @param  list<int>  $purchaseOrderLineIds
     */
    private function assertLinesBelongToOrder(PurchaseOrder $purchaseOrder, array $purchaseOrderLineIds): void
    {
        $valid = $purchaseOrder->lines->pluck('id')->all();
        foreach ($purchaseOrderLineIds as $id) {
            if (! in_array($id, $valid, true)) {
                throw new InvalidArgumentException(__('Invalid purchase order line for this order.'));
            }
        }
    }

    private function sumReceivedQuantityForPurchaseOrderLine(int $purchaseOrderLineId): string
    {
        $sum = GoodsReceiptLine::query()
            ->where('purchase_order_line_id', $purchaseOrderLineId)
            ->whereHas('goodsReceipt', fn ($q) => $q->where('status', GoodsReceiptStatus::Posted))
            ->sum('quantity_received');

        return $sum === null ? '0' : (string) $sum;
    }

    /**
     * When the PO was created from an RFQ, copy the PO line unit cost (actual / booked cost) onto the linked RFQ line
     * after receipt so estimated unit price reflects post-receipt data.
     *
     * @param  array<int, string>  $receivedLineIds  keyed by purchase_order_line_id (values unused; receipt keys only)
     */
    private function syncRfqLineUnitPricesFromPurchaseOrder(PurchaseOrder $purchaseOrder, array $receivedLineIds): void
    {
        if ($purchaseOrder->rfq_id === null) {
            return;
        }

        foreach (array_keys($receivedLineIds) as $purchaseOrderLineId) {
            $poLine = PurchaseOrderLine::query()
                ->whereKey($purchaseOrderLineId)
                ->where('purchase_order_id', $purchaseOrder->id)
                ->first();

            if ($poLine === null || $poLine->rfq_line_id === null || $poLine->unit_cost === null) {
                continue;
            }

            RfqLine::query()->whereKey($poLine->rfq_line_id)->update([
                'unit_price' => $poLine->unit_cost,
            ]);
        }
    }

    private function resolvePurchaseOrderStatus(PurchaseOrder $purchaseOrder): PurchaseOrderStatus
    {
        $anyReceived = false;
        $allComplete = true;

        foreach ($purchaseOrder->lines as $line) {
            $received = $this->sumReceivedQuantityForPurchaseOrderLine((int) $line->id);
            if (bccomp($received, '0', 4) === 1) {
                $anyReceived = true;
            }
            if (bccomp($received, (string) $line->quantity_ordered, 4) === -1) {
                $allComplete = false;
            }
        }

        if (! $anyReceived) {
            return PurchaseOrderStatus::Confirmed;
        }

        if ($allComplete) {
            return PurchaseOrderStatus::Received;
        }

        return PurchaseOrderStatus::PartiallyReceived;
    }
}
