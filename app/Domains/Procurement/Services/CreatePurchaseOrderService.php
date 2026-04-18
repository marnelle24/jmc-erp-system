<?php

namespace App\Domains\Procurement\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\RfqStatus;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreatePurchaseOrderService
{
    public function __construct(
        private readonly AllocatePurchaseOrderReferenceCodeService $referenceCodes,
    ) {}

    /**
     * @param  array{supplier_id: int, rfq_id?: int|null, order_date: string, notes?: string|null, lines: list<array{product_id: int, quantity_ordered: string, unit_cost?: string|null, rfq_line_id?: int|null}>}  $data
     */
    public function execute(int $tenantId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $data): PurchaseOrder {
            $rfq = null;
            if (! empty($data['rfq_id'])) {
                $rfq = Rfq::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey((int) $data['rfq_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($rfq->purchaseOrders()->exists()) {
                    throw new InvalidArgumentException(__('A purchase order already exists for this RFQ.'));
                }

                if ($rfq->status === RfqStatus::PendingForApproval) {
                    throw new InvalidArgumentException(__('Approve this RFQ before creating a purchase order.'));
                }

                if ($rfq->status === RfqStatus::Closed) {
                    throw new InvalidArgumentException(__('This request for quotation is closed.'));
                }

                if ((int) $data['supplier_id'] !== (int) $rfq->supplier_id) {
                    throw new InvalidArgumentException(__('Supplier must match the RFQ supplier.'));
                }
            }

            $referenceCode = $this->referenceCodes->next($tenantId);

            $po = PurchaseOrder::query()->create([
                'tenant_id' => $tenantId,
                'supplier_id' => $data['supplier_id'],
                'reference_code' => $referenceCode,
                'rfq_id' => $data['rfq_id'] ?? null,
                'status' => PurchaseOrderStatus::Confirmed,
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $index => $line) {
                $po->lines()->create([
                    'rfq_line_id' => ! empty($line['rfq_line_id']) ? (int) $line['rfq_line_id'] : null,
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => (string) $line['quantity_ordered'],
                    'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null
                        ? (string) $line['unit_cost']
                        : null,
                    'position' => $index,
                ]);
            }

            if ($rfq !== null) {
                $rfq->update(['status' => RfqStatus::Closed]);
            }

            return $po->load('lines');
        });
    }
}
