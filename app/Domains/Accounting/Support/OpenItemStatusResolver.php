<?php

namespace App\Domains\Accounting\Support;

use App\Enums\AccountingOpenItemStatus;

final class OpenItemStatusResolver
{
    public static function fromAmounts(string $totalAmount, string $amountPaid): AccountingOpenItemStatus
    {
        if (bccomp($totalAmount, '0', 4) !== 1) {
            return AccountingOpenItemStatus::Paid;
        }

        if (bccomp($amountPaid, $totalAmount, 4) !== -1) {
            return AccountingOpenItemStatus::Paid;
        }

        if (bccomp($amountPaid, '0', 4) === 1) {
            return AccountingOpenItemStatus::Partial;
        }

        return AccountingOpenItemStatus::Open;
    }

    public static function remaining(string $totalAmount, string $amountPaid): string
    {
        $rem = bcsub($totalAmount, $amountPaid, 4);

        return bccomp($rem, '0', 4) === -1 ? '0' : $rem;
    }
}
