<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\DTOs\InventoryMovementSourceLink;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryMovement;
use App\Models\SalesShipmentLine;

class ResolveInventoryMovementSourceLinkService
{
    public function resolve(InventoryMovement $movement): InventoryMovementSourceLink
    {
        if ($movement->reference_type === null || $movement->reference_id === null) {
            return new InventoryMovementSourceLink(__('Manual adjustment'));
        }

        return match ($movement->reference_type) {
            GoodsReceiptLine::class => $this->fromGoodsReceiptLine($movement),
            SalesShipmentLine::class => $this->fromSalesShipmentLine($movement),
            default => $this->fallback($movement),
        };
    }

    private function fromGoodsReceiptLine(InventoryMovement $movement): InventoryMovementSourceLink
    {
        $ref = $movement->reference;
        if (! $ref instanceof GoodsReceiptLine) {
            return new InventoryMovementSourceLink(__('Source line unavailable'));
        }

        $ref->loadMissing('goodsReceipt.purchaseOrder');
        $po = $ref->goodsReceipt?->purchaseOrder;

        $label = $po !== null
            ? (($po->reference_code !== null && $po->reference_code !== '') ? $po->reference_code : '#'.$po->id)
            : __('Source line unavailable');

        $url = $po !== null
            ? route('procurement.purchase-orders.show', $po->id)
            : null;

        return new InventoryMovementSourceLink($label, $url);
    }

    private function fromSalesShipmentLine(InventoryMovement $movement): InventoryMovementSourceLink
    {
        $ref = $movement->reference;
        if (! $ref instanceof SalesShipmentLine) {
            return new InventoryMovementSourceLink(__('Source line unavailable'));
        }

        $ref->loadMissing('salesShipment.salesOrder');
        $order = $ref->salesShipment?->salesOrder;

        $label = __('Sales shipment #:shipment · SO #:order', [
            'shipment' => (string) $ref->sales_shipment_id,
            'order' => $order !== null ? (string) $order->id : '?',
        ]);

        $url = $order !== null
            ? route('sales.orders.show', $order->id)
            : null;

        return new InventoryMovementSourceLink($label, $url);
    }

    private function fallback(InventoryMovement $movement): InventoryMovementSourceLink
    {
        $short = class_basename((string) $movement->reference_type);

        return new InventoryMovementSourceLink(
            __(':type #:id', ['type' => $short, 'id' => (string) $movement->reference_id])
        );
    }
}
