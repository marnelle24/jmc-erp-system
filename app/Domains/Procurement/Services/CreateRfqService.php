<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;

class CreateRfqService
{
    /**
     * @param  array{supplier_id: int, title?: string|null, notes?: string|null, lines: list<array{product_id: int, quantity: string, unit_price?: string|null, notes?: string|null}>}  $data
     */
    public function execute(int $tenantId, array $data): Rfq
    {
        return DB::transaction(function () use ($tenantId, $data): Rfq {
            $rfq = Rfq::query()->create([
                'tenant_id' => $tenantId,
                'supplier_id' => $data['supplier_id'],
                'status' => RfqStatus::PendingForApproval,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $rfq->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => (string) $line['quantity'],
                    'unit_price' => isset($line['unit_price']) && $line['unit_price'] !== '' && $line['unit_price'] !== null
                        ? (string) $line['unit_price']
                        : null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $rfq->load('lines');
        });
    }
}
