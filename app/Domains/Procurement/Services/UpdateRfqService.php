<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateRfqService
{
    /**
     * @param  array{title?: string|null, notes?: string|null, lines: list<array{product_id: int, quantity: string, unit_type: string, unit_price?: string|null, notes?: string|null}>}  $data
     */
    public function execute(Rfq $rfq, array $data): Rfq
    {
        if ($rfq->status === RfqStatus::Closed || $rfq->purchaseOrders()->exists()) {
            throw new InvalidArgumentException(__('This RFQ can no longer be edited.'));
        }

        if (! in_array($rfq->status, [RfqStatus::PendingForApproval, RfqStatus::ApprovedNoPo], true)) {
            throw new InvalidArgumentException(__('This RFQ cannot be edited in its current state.'));
        }

        return DB::transaction(function () use ($rfq, $data): Rfq {
            $rfq->update([
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $rfq->lines()->delete();

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
