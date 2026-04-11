<?php

namespace App\Domains\Sales\Services;

use App\Domains\Inventory\Services\GetProductOnHandQuantityService;
use App\Domains\Inventory\Services\PostInventoryMovementService;
use App\Enums\InventoryMovementType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesShipmentStatus;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesShipment;
use App\Models\SalesShipmentLine;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostSalesShipmentService
{
    public function __construct(
        private readonly PostInventoryMovementService $postMovement,
        private readonly GetProductOnHandQuantityService $onHand,
    ) {}

    /**
     * @param  list<array{sales_order_line_id: int, quantity_shipped: string}>  $lines
     */
    public function execute(
        int $tenantId,
        int $salesOrderId,
        array $lines,
        CarbonInterface|string $shippedAt,
        ?string $notes = null,
    ): SalesShipment {
        return DB::transaction(function () use ($tenantId, $salesOrderId, $lines, $shippedAt, $notes): SalesShipment {
            $salesOrder = SalesOrder::query()
                ->where('tenant_id', $tenantId)
                ->with(['lines.product'])
                ->whereKey($salesOrderId)
                ->firstOrFail();

            if ($salesOrder->status === SalesOrderStatus::Cancelled) {
                throw new InvalidArgumentException(__('Cannot ship against a cancelled sales order.'));
            }

            $normalized = $this->normalizeLines($lines);
            if ($normalized === []) {
                throw new InvalidArgumentException(__('Enter at least one positive quantity to ship.'));
            }

            $this->assertLinesBelongToOrder($salesOrder, array_keys($normalized));

            $needByProduct = [];
            foreach ($normalized as $salesOrderLineId => $quantityShipped) {
                $soLine = $salesOrder->lines->firstWhere('id', $salesOrderLineId);
                if (! $soLine instanceof SalesOrderLine) {
                    continue;
                }
                $pid = (int) $soLine->product_id;
                $needByProduct[$pid] = isset($needByProduct[$pid])
                    ? bcadd($needByProduct[$pid], $quantityShipped, 4)
                    : $quantityShipped;
            }

            foreach ($needByProduct as $productId => $need) {
                $available = $this->onHand->execute($tenantId, $productId);
                if (bccomp($available, $need, 4) === -1) {
                    throw new InvalidArgumentException(
                        __('Insufficient stock for one or more products. Available: :qty.', ['qty' => $available])
                    );
                }
            }

            foreach ($normalized as $salesOrderLineId => $quantityShipped) {
                $soLine = $salesOrder->lines->firstWhere('id', $salesOrderLineId);
                if (! $soLine instanceof SalesOrderLine) {
                    continue;
                }

                $already = $this->sumShippedQuantityForSalesOrderLine($salesOrderLineId);
                $ordered = (string) $soLine->quantity_ordered;
                $nextTotal = bcadd($already, $quantityShipped, 4);

                if (bccomp($nextTotal, $ordered, 4) === 1) {
                    throw new InvalidArgumentException(
                        __('Cannot ship more than ordered for :product.', ['product' => $soLine->product?->name ?? '#'.$soLine->product_id])
                    );
                }
            }

            $shippedAtCarbon = $shippedAt instanceof CarbonInterface
                ? $shippedAt
                : Carbon::parse((string) $shippedAt);

            $shipment = SalesShipment::query()->create([
                'tenant_id' => $tenantId,
                'sales_order_id' => $salesOrder->id,
                'status' => SalesShipmentStatus::Posted,
                'shipped_at' => $shippedAtCarbon->toDateTimeString(),
                'notes' => $notes,
            ]);

            foreach ($normalized as $salesOrderLineId => $quantityShipped) {
                $soLine = $salesOrder->lines->firstWhere('id', $salesOrderLineId);
                if (! $soLine instanceof SalesOrderLine) {
                    continue;
                }

                $shipmentLine = SalesShipmentLine::query()->create([
                    'sales_shipment_id' => $shipment->id,
                    'sales_order_line_id' => $salesOrderLineId,
                    'quantity_shipped' => $quantityShipped,
                ]);

                $shipmentLine->load('salesOrderLine.product');

                $negativeQty = bcmul($quantityShipped, '-1', 4);

                $this->postMovement->execute(
                    $tenantId,
                    $soLine->product_id,
                    $negativeQty,
                    InventoryMovementType::Issue,
                    __('Sales shipment #:shipment (SO #:order)', [
                        'shipment' => $shipment->id,
                        'order' => $salesOrder->id,
                    ]),
                    SalesShipmentLine::class,
                    $shipmentLine->id,
                );
            }

            $salesOrder->refresh()->load('lines');
            $salesOrder->update([
                'status' => $this->resolveSalesOrderStatus($salesOrder),
            ]);

            return $shipment->load('lines');
        });
    }

    /**
     * @param  list<array{sales_order_line_id: int, quantity_shipped: string}>  $lines
     * @return array<int, string> keyed by sales_order_line_id
     */
    private function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            $qty = isset($row['quantity_shipped']) ? (string) $row['quantity_shipped'] : '0';
            if (bccomp($qty, '0', 4) !== 1) {
                continue;
            }
            $lineId = (int) $row['sales_order_line_id'];
            if ($lineId < 1) {
                continue;
            }
            $out[$lineId] = isset($out[$lineId]) ? bcadd($out[$lineId], $qty, 4) : $qty;
        }

        return $out;
    }

    /**
     * @param  list<int>  $salesOrderLineIds
     */
    private function assertLinesBelongToOrder(SalesOrder $salesOrder, array $salesOrderLineIds): void
    {
        $valid = $salesOrder->lines->pluck('id')->all();
        foreach ($salesOrderLineIds as $id) {
            if (! in_array($id, $valid, true)) {
                throw new InvalidArgumentException(__('Invalid sales order line for this order.'));
            }
        }
    }

    private function sumShippedQuantityForSalesOrderLine(int $salesOrderLineId): string
    {
        $sum = SalesShipmentLine::query()
            ->where('sales_order_line_id', $salesOrderLineId)
            ->whereHas('salesShipment', fn ($q) => $q->where('status', SalesShipmentStatus::Posted))
            ->sum('quantity_shipped');

        return $sum === null ? '0' : (string) $sum;
    }

    private function resolveSalesOrderStatus(SalesOrder $salesOrder): SalesOrderStatus
    {
        $anyShipped = false;
        $allComplete = true;

        foreach ($salesOrder->lines as $line) {
            $shipped = $this->sumShippedQuantityForSalesOrderLine((int) $line->id);
            if (bccomp($shipped, '0', 4) === 1) {
                $anyShipped = true;
            }
            if (bccomp($shipped, (string) $line->quantity_ordered, 4) === -1) {
                $allComplete = false;
            }
        }

        if (! $anyShipped) {
            return SalesOrderStatus::Confirmed;
        }

        if ($allComplete) {
            return SalesOrderStatus::Fulfilled;
        }

        return SalesOrderStatus::PartiallyFulfilled;
    }
}
