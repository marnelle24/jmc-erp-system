<?php

namespace App\Domains\Accounting\Services;

use App\Enums\SupplierPaymentRunStatus;
use App\Models\SupplierPaymentRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelSupplierPaymentRunService
{
    public function execute(int $tenantId, int $runId): SupplierPaymentRun
    {
        return DB::transaction(function () use ($tenantId, $runId): SupplierPaymentRun {
            /** @var SupplierPaymentRun $run */
            $run = SupplierPaymentRun::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($runId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($run->status, [SupplierPaymentRunStatus::Draft, SupplierPaymentRunStatus::Approved], true)) {
                throw new InvalidArgumentException(__('Only draft or approved runs can be cancelled.'));
            }

            $run->status = SupplierPaymentRunStatus::Cancelled;
            $run->save();

            return $run;
        });
    }
}
