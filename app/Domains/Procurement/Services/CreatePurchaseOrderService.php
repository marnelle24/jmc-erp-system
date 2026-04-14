<?php

namespace App\Domains\Procurement\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderService
{
    /**
     * @param  array{supplier_id: int, rfq_id?: int|null, order_date: string, notes?: string|null, lines: list<array{product_id: int, quantity_ordered: string, unit_cost?: string|null, rfq_line_id?: int|null}>}  $data
     */
    public function execute(int $tenantId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $data): PurchaseOrder {
            $po = PurchaseOrder::query()->create([
                'tenant_id' => $tenantId,
                'supplier_id' => $data['supplier_id'],
                'rfq_id' => $data['rfq_id'] ?? null,
                'status' => PurchaseOrderStatus::Confirmed,
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $index => $line) {
                $po->lines()->create([
                    'rfq_line_id' => isset($line['rfq_line_id']) ? (int) $line['rfq_line_id'] : null,
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => (string) $line['quantity_ordered'],
                    'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null
                        ? (string) $line['unit_cost']
                        : null,
                    'position' => $index,
                ]);
            }

            return $po->load('lines');
        });
    }
}
