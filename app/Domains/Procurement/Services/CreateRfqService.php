<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;

class CreateRfqService
{
    public function __construct(
        private readonly AllocateRfqReferenceCodeService $referenceCodes,
    ) {}

    /**
     * @param  array{supplier_id: int, title?: string|null, notes?: string|null, lines: list<array{product_id: int, quantity: string, unit_type: string, unit_price?: string|null, notes?: string|null}>}  $data
     */
    public function execute(int $tenantId, array $data, int $createdByUserId): Rfq
    {
        return DB::transaction(function () use ($tenantId, $data, $createdByUserId): Rfq {
            $referenceCode = $this->referenceCodes->next($tenantId);

            $rfq = Rfq::query()->create([
                'tenant_id' => $tenantId,
                'supplier_id' => $data['supplier_id'],
                'reference_code' => $referenceCode,
                'status' => RfqStatus::PendingForApproval,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdByUserId,
            ]);

            foreach ($data['lines'] as $line) {
                $rfq->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => (string) $line['quantity'],
                    'unit_type' => $line['unit_type'],
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
