<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Models\AccountsPayable;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordSupplierPaymentService
{
    /**
     * @param  list<array{accounts_payable_id: int, amount: string}>  $allocations
     */
    public function execute(
        int $tenantId,
        int $supplierId,
        string $paymentAmount,
        string $paidAt,
        ?string $reference,
        ?string $notes,
        string $paymentMethod,
        array $allocations,
    ): SupplierPayment {
        if ($allocations === []) {
            throw new InvalidArgumentException(__('Add at least one allocation to an open payable.'));
        }

        $sum = '0';
        foreach ($allocations as $row) {
            $amt = (string) ($row['amount'] ?? '0');
            if (bccomp($amt, '0', 4) !== 1) {
                continue;
            }
            $sum = bcadd($sum, $amt, 4);
        }

        if (bccomp($sum, '0', 4) !== 1) {
            throw new InvalidArgumentException(__('Allocation amounts must sum to a positive total.'));
        }

        if (bccomp($sum, $paymentAmount, 4) !== 0) {
            throw new InvalidArgumentException(__('Allocation total must equal the payment amount.'));
        }

        return DB::transaction(function () use ($tenantId, $supplierId, $paymentAmount, $paidAt, $reference, $notes, $paymentMethod, $allocations): SupplierPayment {
            $payment = SupplierPayment::query()->create([
                'tenant_id' => $tenantId,
                'supplier_id' => $supplierId,
                'amount' => $paymentAmount,
                'payment_method' => $paymentMethod,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            foreach ($allocations as $row) {
                $apId = (int) ($row['accounts_payable_id'] ?? 0);
                $amt = (string) ($row['amount'] ?? '0');
                if (bccomp($amt, '0', 4) !== 1) {
                    continue;
                }
                if ($apId < 1) {
                    throw new InvalidArgumentException(__('Invalid accounts payable line.'));
                }

                /** @var AccountsPayable $ap */
                $ap = AccountsPayable::query()
                    ->where('tenant_id', $tenantId)
                    ->where('supplier_id', $supplierId)
                    ->whereKey($apId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $remaining = OpenItemStatusResolver::remaining((string) $ap->total_amount, (string) $ap->amount_paid);
                if (bccomp($amt, $remaining, 4) === 1) {
                    throw new InvalidArgumentException(
                        __('Cannot allocate more than the remaining balance on payable #:id.', ['id' => $ap->id])
                    );
                }

                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $payment->id,
                    'accounts_payable_id' => $ap->id,
                    'amount' => $amt,
                ]);

                $newPaid = bcadd((string) $ap->amount_paid, $amt, 4);
                $ap->amount_paid = $newPaid;
                $ap->status = OpenItemStatusResolver::fromAmounts((string) $ap->total_amount, $newPaid);
                $ap->save();
            }

            return $payment->load('allocations');
        });
    }
}
