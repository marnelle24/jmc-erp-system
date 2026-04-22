<?php

namespace App\Domains\Accounting\Services;

use App\Enums\AccountingOpenItemStatus;
use App\Enums\SupplierPaymentRunStatus;
use App\Models\AccountsPayable;
use App\Models\SupplierPaymentRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildSupplierPaymentRunService
{
    /**
     * @param  list<int>  $selectedPayableIds
     */
    public function execute(
        int $tenantId,
        int $createdBy,
        string $scheduledFor,
        ?string $paymentMethod,
        ?int $supplierId = null,
        ?string $dueDateTo = null,
        ?string $notes = null,
        array $selectedPayableIds = [],
    ): SupplierPaymentRun {
        return DB::transaction(function () use (
            $tenantId,
            $createdBy,
            $scheduledFor,
            $paymentMethod,
            $supplierId,
            $dueDateTo,
            $notes,
            $selectedPayableIds
        ): SupplierPaymentRun {
            $run = SupplierPaymentRun::query()->create([
                'tenant_id' => $tenantId,
                'reference_code' => $this->nextReferenceCode(),
                'status' => SupplierPaymentRunStatus::Draft,
                'scheduled_for' => $scheduledFor,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            $payables = $this->eligiblePayables($tenantId, $supplierId, $dueDateTo, $selectedPayableIds);

            $proposed = '0';
            foreach ($payables as $payable) {
                $remaining = bcsub((string) $payable->total_amount, (string) $payable->amount_paid, 4);
                if (bccomp($remaining, '0', 4) !== 1) {
                    continue;
                }

                $run->items()->create([
                    'tenant_id' => $tenantId,
                    'accounts_payable_id' => $payable->id,
                    'supplier_id' => $payable->supplier_id,
                    'planned_amount' => $remaining,
                    'executed_amount' => '0',
                ]);

                $proposed = bcadd($proposed, $remaining, 4);
            }

            $run->proposed_amount = $proposed;
            $run->save();

            return $run->load(['items.accountsPayable.supplier']);
        });
    }

    /**
     * @param  list<int>  $selectedPayableIds
     * @return Collection<int, AccountsPayable>
     */
    private function eligiblePayables(
        int $tenantId,
        ?int $supplierId,
        ?string $dueDateTo,
        array $selectedPayableIds
    ): Collection {
        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($dueDateTo !== null && Schema::hasColumn('accounts_payable', 'due_date'), fn ($query) => $query->whereDate('due_date', '<=', $dueDateTo))
            ->when(
                $selectedPayableIds !== [],
                fn ($query) => $query->whereIn('id', $selectedPayableIds)
            )
            ->with('supplier')
            ->when(Schema::hasColumn('accounts_payable', 'due_date'), fn ($query) => $query->orderBy('due_date'))
            ->orderBy('posted_at')
            ->get();
    }

    private function nextReferenceCode(): string
    {
        return sprintf('PR-%s-%s', now()->format('YmdHis'), strtoupper(str()->random(4)));
    }
}
