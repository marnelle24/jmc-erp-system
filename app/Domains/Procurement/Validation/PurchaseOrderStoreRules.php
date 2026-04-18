<?php

namespace App\Domains\Procurement\Validation;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PurchaseOrderStoreRules
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public static function rules(int $tenantId): array
    {
        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'rfq_id' => [
                'nullable',
                'integer',
                Rule::exists('rfqs', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'order_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'lines.*.quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.rfq_line_id' => [
                'nullable',
                'integer',
                Rule::exists('rfq_lines', 'id'),
            ],
        ];
    }

    public static function withValidatorAfter(Validator $validator, int $tenantId): void
    {
        $validator->after(function (Validator $validator) use ($tenantId): void {
            $data = $validator->getData();
            $rfqId = $data['rfq_id'] ?? null;
            $lines = $data['lines'] ?? [];

            if ($rfqId === null) {
                if (is_array($lines)) {
                    foreach ($lines as $index => $line) {
                        if (is_array($line) && ! empty($line['rfq_line_id'])) {
                            $validator->errors()->add(
                                'lines.'.$index.'.rfq_line_id',
                                __('Remove RFQ line links or choose an RFQ to import.'),
                            );
                        }
                    }
                }

                return;
            }

            if (! is_array($lines)) {
                return;
            }

            $rfq = Rfq::query()
                ->where('tenant_id', $tenantId)
                ->with('lines')
                ->find((int) $rfqId);

            if ($rfq === null) {
                return;
            }

            if ($rfq->purchaseOrders()->exists()) {
                $validator->errors()->add('rfq_id', __('A purchase order already exists for this RFQ.'));

                return;
            }

            if ($rfq->status === RfqStatus::PendingForApproval) {
                $validator->errors()->add('rfq_id', __('Approve this RFQ before creating a purchase order.'));

                return;
            }

            if ($rfq->status === RfqStatus::Closed) {
                $validator->errors()->add('rfq_id', __('This request for quotation is closed.'));

                return;
            }

            if ((int) ($data['supplier_id'] ?? 0) !== (int) $rfq->supplier_id) {
                $validator->errors()->add('supplier_id', __('Supplier must match the RFQ supplier.'));
            }

            if ($rfq->lines->isEmpty()) {
                $validator->errors()->add('rfq_id', __('This request for quotation has no line items.'));

                return;
            }

            $expectedById = $rfq->lines->keyBy('id');
            $expectedIds = $expectedById->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();
            $seenIds = [];

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $rlId = $line['rfq_line_id'] ?? null;
                if ($rlId === null) {
                    $validator->errors()->add(
                        'lines.'.$index.'.rfq_line_id',
                        __('Each line must stay linked to the RFQ when creating from an RFQ.'),
                    );

                    continue;
                }

                $rlId = (int) $rlId;
                if (! $expectedById->has($rlId)) {
                    $validator->errors()->add('lines.'.$index.'.rfq_line_id', __('Invalid RFQ line reference.'));

                    continue;
                }

                if (in_array($rlId, $seenIds, true)) {
                    $validator->errors()->add('lines.'.$index.'.rfq_line_id', __('Duplicate RFQ line.'));

                    continue;
                }

                $seenIds[] = $rlId;
                $rfqLine = $expectedById->get($rlId);
                if ($rfqLine !== null && (int) $line['product_id'] !== (int) $rfqLine->product_id) {
                    $validator->errors()->add('lines.'.$index.'.product_id', __('Product must match the RFQ line.'));
                }
            }

            sort($seenIds);
            if ($seenIds !== $expectedIds) {
                $validator->errors()->add('lines', __('Include every RFQ line exactly once.'));
            }
        });
    }
}
