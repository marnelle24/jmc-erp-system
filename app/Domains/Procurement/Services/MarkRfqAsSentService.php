<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarkRfqAsSentService
{
    public function execute(Rfq $rfq, int $sentByUserId): Rfq
    {
        if ($rfq->status !== RfqStatus::ApprovedNoPo) {
            throw new InvalidArgumentException(__('Only approved RFQs can be marked as sent.'));
        }

        if ($rfq->sent_at !== null) {
            throw new InvalidArgumentException(__('This RFQ has already been marked as sent.'));
        }

        return DB::transaction(function () use ($rfq, $sentByUserId): Rfq {
            $rfq->update([
                'status' => RfqStatus::Sent,
                'sent_by' => $sentByUserId,
                'sent_at' => now(),
            ]);

            return $rfq->fresh(['supplier', 'creator', 'approver', 'sender', 'lines.product', 'purchaseOrders']);
        });
    }
}
