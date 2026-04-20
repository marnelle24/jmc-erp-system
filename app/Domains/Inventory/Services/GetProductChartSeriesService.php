<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\DTOs\ProductChartSeries;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use Carbon\Carbon;

class GetProductChartSeriesService
{
    public function execute(int $tenantId, int $productId, string $dateFrom, string $dateTo): ProductChartSeries
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        return new ProductChartSeries(
            inventoryBalance: $this->inventoryBalanceSeries($tenantId, $productId, $from, $to),
            purchaseUnitCost: $this->purchaseUnitCostSeries($tenantId, $productId, $from, $to),
            saleUnitPrice: $this->saleUnitPriceSeries($tenantId, $productId, $from, $to),
        );
    }

    /**
     * @return list<array{t: string, y: float}>
     */
    private function inventoryBalanceSeries(int $tenantId, int $productId, Carbon $from, Carbon $to): array
    {
        $carryIn = (float) InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('created_at', '<', $from)
            ->sum('quantity');

        $movements = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'quantity', 'created_at']);

        $points = [];
        $balance = $carryIn;
        $points[] = [
            't' => $from->toIso8601String(),
            'y' => round($balance, 4),
        ];

        foreach ($movements as $movement) {
            $balance += (float) $movement->quantity;
            $points[] = [
                't' => $movement->created_at->toIso8601String(),
                'y' => round($balance, 4),
            ];
        }

        return $points;
    }

    /**
     * @return list<array{t: string, y: float}>
     */
    private function purchaseUnitCostSeries(int $tenantId, int $productId, Carbon $from, Carbon $to): array
    {
        $lines = PurchaseOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseOrder', function ($q) use ($tenantId, $from, $to): void {
                $q->where('tenant_id', $tenantId)
                    ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->with('purchaseOrder')
            ->orderBy('purchase_order_id')
            ->orderBy('id')
            ->get();

        $points = [];
        foreach ($lines as $line) {
            if ($line->unit_cost === null) {
                continue;
            }

            $orderDate = $line->purchaseOrder?->order_date;
            if ($orderDate === null) {
                continue;
            }

            $points[] = [
                't' => $orderDate->toDateString(),
                'y' => round((float) $line->unit_cost, 4),
            ];
        }

        return $points;
    }

    /**
     * @return list<array{t: string, y: float}>
     */
    private function saleUnitPriceSeries(int $tenantId, int $productId, Carbon $from, Carbon $to): array
    {
        $lines = SalesOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('salesOrder', function ($q) use ($tenantId, $from, $to): void {
                $q->where('tenant_id', $tenantId)
                    ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->with('salesOrder')
            ->orderBy('sales_order_id')
            ->orderBy('id')
            ->get();

        $points = [];
        foreach ($lines as $line) {
            if ($line->unit_price === null) {
                continue;
            }

            $orderDate = $line->salesOrder?->order_date;
            if ($orderDate === null) {
                continue;
            }

            $points[] = [
                't' => $orderDate->toDateString(),
                'y' => round((float) $line->unit_price, 4),
            ];
        }

        return $points;
    }
}
