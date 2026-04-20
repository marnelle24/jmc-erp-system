<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\DTOs\ProductActivityTimelineEntry;
use App\Enums\GoodsReceiptStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesShipmentStatus;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrderLine;
use App\Models\RfqLine;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrderLine;
use App\Models\SalesShipmentLine;
use Carbon\Carbon;

class BuildProductActivityTimelineService
{
    private const MAX_ENTRIES = 500;

    public function __construct(
        private readonly ResolveInventoryMovementSourceLinkService $resolveMovementSource,
    ) {}

    /**
     * @return list<ProductActivityTimelineEntry>
     */
    public function execute(int $tenantId, int $productId): array
    {
        $entries = [];

        $this->appendRfqLines($tenantId, $productId, $entries);
        $this->appendPurchaseOrderLines($tenantId, $productId, $entries);
        $this->appendGoodsReceiptLines($tenantId, $productId, $entries);
        $this->appendInventoryMovements($tenantId, $productId, $entries);
        $this->appendSalesOrderLines($tenantId, $productId, $entries);
        $this->appendSalesShipmentLines($tenantId, $productId, $entries);
        $this->appendSalesInvoiceLines($tenantId, $productId, $entries);

        usort($entries, function (ProductActivityTimelineEntry $a, ProductActivityTimelineEntry $b): int {
            $cmp = strcmp($b->occurredAtIso, $a->occurredAtIso);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b->tiebreaker <=> $a->tiebreaker;
        });

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendRfqLines(int $tenantId, int $productId, array &$entries): void
    {
        $lines = RfqLine::query()
            ->where('product_id', $productId)
            ->whereHas('rfq', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['rfq.supplier'])
            ->get();

        foreach ($lines as $line) {
            $rfq = $line->rfq;
            if ($rfq === null) {
                continue;
            }

            $occurredAt = $rfq->sent_at ?? $rfq->created_at ?? now();
            $ref = ($rfq->reference_code !== null && $rfq->reference_code !== '') ? $rfq->reference_code : '#'.$rfq->id;
            $supplier = $rfq->supplier?->name;

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'rfq',
                title: __('RFQ :ref', ['ref' => $ref]),
                subtitle: $supplier !== null
                    ? __('Supplier: :name · Qty :qty', ['name' => $supplier, 'qty' => (string) $line->quantity])
                    : __('Qty :qty', ['qty' => (string) $line->quantity]),
                url: route('procurement.rfqs.show', $rfq->id),
                quantity: (string) $line->quantity,
                amount: $line->unit_price !== null ? (string) $line->unit_price : null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $line->id,
            );
        }
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendPurchaseOrderLines(int $tenantId, int $productId, array &$entries): void
    {
        $lines = PurchaseOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['purchaseOrder.supplier'])
            ->get();

        foreach ($lines as $line) {
            $po = $line->purchaseOrder;
            if ($po === null) {
                continue;
            }

            $occurredAt = $po->order_date?->startOfDay() ?? $po->created_at ?? now();
            $ref = ($po->reference_code !== null && $po->reference_code !== '') ? $po->reference_code : '#'.$po->id;
            $supplier = $po->supplier?->name;

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'purchase_order',
                title: __('Purchase order :ref', ['ref' => $ref]),
                subtitle: $supplier !== null
                    ? __('Supplier: :name · Ordered :qty', ['name' => $supplier, 'qty' => (string) $line->quantity_ordered])
                    : __('Ordered :qty', ['qty' => (string) $line->quantity_ordered]),
                url: route('procurement.purchase-orders.show', $po->id),
                quantity: (string) $line->quantity_ordered,
                amount: $line->unit_cost !== null ? (string) $line->unit_cost : null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $line->id + 100,
            );
        }
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendGoodsReceiptLines(int $tenantId, int $productId, array &$entries): void
    {
        $lines = GoodsReceiptLine::query()
            ->whereHas('purchaseOrderLine', fn ($q) => $q->where('product_id', $productId))
            ->whereHas('goodsReceipt', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', GoodsReceiptStatus::Posted))
            ->with(['goodsReceipt.purchaseOrder', 'purchaseOrderLine'])
            ->get();

        foreach ($lines as $line) {
            $gr = $line->goodsReceipt;
            $po = $gr?->purchaseOrder;
            if ($gr === null || $po === null) {
                continue;
            }

            $occurredAt = $gr->received_at ?? $gr->created_at ?? now();
            $poRef = ($po->reference_code !== null && $po->reference_code !== '') ? $po->reference_code : '#'.$po->id;

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'goods_receipt',
                title: __('Goods receipt · PO :ref', ['ref' => $poRef]),
                subtitle: __('Received :qty', ['qty' => (string) $line->quantity_received]),
                url: route('procurement.purchase-orders.show', $po->id),
                quantity: (string) $line->quantity_received,
                amount: $line->unit_cost !== null ? (string) $line->unit_cost : null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $line->id + 200,
            );
        }
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendInventoryMovements(int $tenantId, int $productId, array &$entries): void
    {
        $movements = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->with('reference')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        foreach ($movements as $movement) {
            $link = $this->resolveMovementSource->resolve($movement);
            $occurredAt = $movement->created_at ?? now();

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'inventory_movement',
                title: __('Inventory: :type', ['type' => $movement->movement_type->value]),
                subtitle: $link->label,
                url: $link->url,
                quantity: (string) $movement->quantity,
                amount: null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $movement->id + 300,
            );
        }
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendSalesOrderLines(int $tenantId, int $productId, array &$entries): void
    {
        $lines = SalesOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('salesOrder', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['salesOrder.customer'])
            ->get();

        foreach ($lines as $line) {
            $order = $line->salesOrder;
            if ($order === null) {
                continue;
            }

            $occurredAt = $order->order_date?->startOfDay() ?? $order->created_at ?? now();
            $customer = $order->customer?->name;

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'sales_order',
                title: __('Sales order #:id', ['id' => (string) $order->id]),
                subtitle: $customer !== null
                    ? __('Customer: :name · Ordered :qty', ['name' => $customer, 'qty' => (string) $line->quantity_ordered])
                    : __('Ordered :qty', ['qty' => (string) $line->quantity_ordered]),
                url: route('sales.orders.show', $order->id),
                quantity: (string) $line->quantity_ordered,
                amount: $line->unit_price !== null ? (string) $line->unit_price : null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $line->id + 400,
            );
        }
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendSalesShipmentLines(int $tenantId, int $productId, array &$entries): void
    {
        $lines = SalesShipmentLine::query()
            ->whereHas('salesOrderLine', fn ($q) => $q->where('product_id', $productId))
            ->whereHas('salesShipment', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', SalesShipmentStatus::Posted))
            ->with(['salesShipment.salesOrder', 'salesOrderLine'])
            ->get();

        foreach ($lines as $line) {
            $shipment = $line->salesShipment;
            $so = $shipment?->salesOrder;
            if ($shipment === null || $so === null) {
                continue;
            }

            $occurredAt = $shipment->shipped_at ?? $shipment->created_at ?? now();

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'sales_shipment',
                title: __('Shipment · Sales order #:id', ['id' => (string) $so->id]),
                subtitle: __('Shipped :qty', ['qty' => (string) $line->quantity_shipped]),
                url: route('sales.orders.show', $so->id),
                quantity: (string) $line->quantity_shipped,
                amount: null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $line->id + 500,
            );
        }
    }

    /**
     * @param  list<ProductActivityTimelineEntry>  $entries
     */
    private function appendSalesInvoiceLines(int $tenantId, int $productId, array &$entries): void
    {
        $lines = SalesInvoiceLine::query()
            ->whereHas('salesOrderLine', fn ($q) => $q->where('product_id', $productId))
            ->whereHas('salesInvoice', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', SalesInvoiceStatus::Issued))
            ->with(['salesInvoice.salesOrder', 'salesOrderLine'])
            ->get();

        foreach ($lines as $line) {
            $invoice = $line->salesInvoice;
            $so = $invoice?->salesOrder;
            if ($invoice === null || $so === null) {
                continue;
            }

            $occurredAt = $invoice->issued_at ?? $invoice->created_at ?? now();

            $entries[] = new ProductActivityTimelineEntry(
                occurredAtIso: Carbon::parse($occurredAt)->toIso8601String(),
                category: 'sales_invoice',
                title: __('Invoice · Sales order #:id', ['id' => (string) $so->id]),
                subtitle: __('Invoiced :qty', ['qty' => (string) $line->quantity_invoiced]),
                url: route('sales.orders.show', $so->id),
                quantity: (string) $line->quantity_invoiced,
                amount: $line->unit_price !== null ? (string) $line->unit_price : null,
                tiebreaker: (int) (Carbon::parse($occurredAt)->timestamp * 1000) + $line->id + 600,
            );
        }
    }
}
