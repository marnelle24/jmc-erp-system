<?php

namespace App\Domains\Sales\Services;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class CreateSalesOrderService
{
    /**
     * @param  array{customer_id: int, order_date: string, notes?: string|null, lines: list<array{product_id: int, quantity_ordered: string, unit_price?: string|null}>}  $data
     */
    public function execute(int $tenantId, array $data): SalesOrder
    {
        return DB::transaction(function () use ($tenantId, $data): SalesOrder {
            $order = SalesOrder::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'status' => SalesOrderStatus::Confirmed,
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $index => $line) {
                $order->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => (string) $line['quantity_ordered'],
                    'unit_price' => isset($line['unit_price']) && $line['unit_price'] !== '' && $line['unit_price'] !== null
                        ? (string) $line['unit_price']
                        : null,
                    'position' => $index,
                ]);
            }

            return $order->load('lines');
        });
    }
}
