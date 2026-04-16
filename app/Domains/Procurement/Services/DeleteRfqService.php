<?php

namespace App\Domains\Procurement\Services;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteRfqService
{
    /**
     * Permanently deletes the RFQ. Related `rfq_lines` rows are removed by the
     * database foreign key (ON DELETE CASCADE).
     *
     * @throws InvalidArgumentException When the RFQ must not be deleted.
     */
    public function execute(Rfq $rfq): void
    {
        if ($rfq->status === RfqStatus::Closed || $rfq->purchaseOrders()->exists()) {
            throw new InvalidArgumentException(__('This RFQ can no longer be deleted.'));
        }

        if (! in_array($rfq->status, [RfqStatus::PendingForApproval, RfqStatus::ApprovedNoPo], true)) {
            throw new InvalidArgumentException(__('This RFQ cannot be deleted in its current state.'));
        }

        DB::transaction(static function () use ($rfq): void {
            $rfq->delete();
        });
    }
}
