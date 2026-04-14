<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveRfqService
{
    public function execute(Rfq $rfq, int $approvedByUserId): Rfq
    {
        if ($rfq->approved_by !== null) {
            throw new InvalidArgumentException(__('This RFQ is already approved.'));
        }

        if ($rfq->status !== RfqStatus::PendingForApproval) {
            throw new InvalidArgumentException(__('Only RFQs pending approval can be approved.'));
        }

        return DB::transaction(function () use ($rfq, $approvedByUserId): Rfq {
            $rfq->update([
                'approved_by' => $approvedByUserId,
                'status' => RfqStatus::ApprovedNoPo,
            ]);

            return $rfq->fresh(['creator', 'approver', 'supplier', 'lines.product']);
        });
    }
}
