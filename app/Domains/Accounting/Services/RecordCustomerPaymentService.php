<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Models\AccountsReceivable;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordCustomerPaymentService
{
    /**
     * @param  list<array{accounts_receivable_id: int, amount: string}>  $allocations
     */
    public function execute(
        int $tenantId,
        int $customerId,
        string $paymentAmount,
        string $paidAt,
        ?string $reference,
        ?string $notes,
        array $allocations,
    ): CustomerPayment {
        if ($allocations === []) {
            throw new InvalidArgumentException(__('Add at least one allocation to an open receivable.'));
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

        return DB::transaction(function () use ($tenantId, $customerId, $paymentAmount, $paidAt, $reference, $notes, $allocations): CustomerPayment {
            $payment = CustomerPayment::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'amount' => $paymentAmount,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            foreach ($allocations as $row) {
                $arId = (int) ($row['accounts_receivable_id'] ?? 0);
                $amt = (string) ($row['amount'] ?? '0');
                if (bccomp($amt, '0', 4) !== 1) {
                    continue;
                }
                if ($arId < 1) {
                    throw new InvalidArgumentException(__('Invalid accounts receivable line.'));
                }

                /** @var AccountsReceivable $ar */
                $ar = AccountsReceivable::query()
                    ->where('tenant_id', $tenantId)
                    ->where('customer_id', $customerId)
                    ->whereKey($arId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $remaining = OpenItemStatusResolver::remaining((string) $ar->total_amount, (string) $ar->amount_paid);
                if (bccomp($amt, $remaining, 4) === 1) {
                    throw new InvalidArgumentException(
                        __('Cannot allocate more than the remaining balance on receivable #:id.', ['id' => $ar->id])
                    );
                }

                CustomerPaymentAllocation::query()->create([
                    'customer_payment_id' => $payment->id,
                    'accounts_receivable_id' => $ar->id,
                    'amount' => $amt,
                ]);

                $newPaid = bcadd((string) $ar->amount_paid, $amt, 4);
                $ar->amount_paid = $newPaid;
                $ar->status = OpenItemStatusResolver::fromAmounts((string) $ar->total_amount, $newPaid);
                $ar->save();
            }

            return $payment->load('allocations');
        });
    }
}
