<?php

namespace App\Domains\Accounting\Services;

use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentRunStatus;
use App\Models\SupplierPaymentRun;
use App\Models\SupplierPaymentRunItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExecuteSupplierPaymentRunService
{
    public function __construct(
        private readonly RecordSupplierPaymentService $recordSupplierPaymentService
    ) {}

    public function execute(int $tenantId, int $runId): SupplierPaymentRun
    {
        return DB::transaction(function () use ($tenantId, $runId): SupplierPaymentRun {
            /** @var SupplierPaymentRun $run */
            $run = SupplierPaymentRun::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($runId)
                ->lockForUpdate()
                ->with(['items.accountsPayable'])
                ->firstOrFail();

            if (! in_array($run->status, [SupplierPaymentRunStatus::Approved, SupplierPaymentRunStatus::Processing], true)) {
                throw new InvalidArgumentException(__('Only approved payment runs can be executed.'));
            }

            if ($run->items->isEmpty()) {
                throw new InvalidArgumentException(__('Payment run has no items to execute.'));
            }

            $run->status = SupplierPaymentRunStatus::Processing;
            $run->save();

            $executedTotal = '0';
            $method = $run->payment_method?->value ?? SupplierPaymentMethod::BankTransfer->value;

            /** @var Collection<int, Collection<int, SupplierPaymentRunItem>> $groups */
            $groups = $run->items->groupBy('supplier_id');
            foreach ($groups as $supplierId => $items) {
                $allocations = [];
                $paymentTotal = '0';

                foreach ($items as $item) {
                    if ($item->supplier_payment_id !== null) {
                        continue;
                    }

                    $remaining = bcsub((string) $item->accountsPayable->total_amount, (string) $item->accountsPayable->amount_paid, 4);
                    $planned = (string) $item->planned_amount;
                    $alloc = bccomp($planned, $remaining, 4) === 1 ? $remaining : $planned;

                    if (bccomp($alloc, '0', 4) !== 1) {
                        continue;
                    }

                    $allocations[] = [
                        'accounts_payable_id' => $item->accounts_payable_id,
                        'amount' => $alloc,
                    ];
                    $paymentTotal = bcadd($paymentTotal, $alloc, 4);
                }

                if ($allocations === []) {
                    continue;
                }

                $payment = $this->recordSupplierPaymentService->execute(
                    $tenantId,
                    (int) $supplierId,
                    $paymentTotal,
                    now()->toDateTimeString(),
                    $run->reference_code,
                    __('Payment run :reference', ['reference' => $run->reference_code]),
                    $method,
                    $allocations,
                );

                foreach ($items as $item) {
                    foreach ($allocations as $allocation) {
                        if ($allocation['accounts_payable_id'] !== $item->accounts_payable_id) {
                            continue;
                        }
                        $item->executed_amount = $allocation['amount'];
                        $item->supplier_payment_id = $payment->id;
                        $item->save();
                        $executedTotal = bcadd($executedTotal, (string) $allocation['amount'], 4);
                        break;
                    }
                }
            }

            $run->status = SupplierPaymentRunStatus::Completed;
            $run->executed_amount = $executedTotal;
            $run->executed_at = now();
            $run->save();

            return $run->load(['items.accountsPayable', 'items.supplierPayment']);
        });
    }
}
