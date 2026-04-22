<?php

namespace App\Domains\Accounting\Services;

use App\Enums\SupplierPaymentRunStatus;
use App\Models\SupplierPaymentRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveSupplierPaymentRunService
{
    public function execute(int $tenantId, int $runId, int $approvedBy): SupplierPaymentRun
    {
        return DB::transaction(function () use ($tenantId, $runId, $approvedBy): SupplierPaymentRun {
            /** @var SupplierPaymentRun $run */
            $run = SupplierPaymentRun::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($runId)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ($run->status !== SupplierPaymentRunStatus::Draft) {
                throw new InvalidArgumentException(__('Only draft payment runs can be approved.'));
            }

            if ($run->items->isEmpty()) {
                throw new InvalidArgumentException(__('Cannot approve an empty payment run.'));
            }

            $total = '0';
            foreach ($run->items as $item) {
                $total = bcadd($total, (string) $item->planned_amount, 4);
            }

            $run->status = SupplierPaymentRunStatus::Approved;
            $run->approved_by = $approvedBy;
            $run->approved_at = now();
            $run->approved_amount = $total;
            $run->save();

            return $run;
        });
    }
}
