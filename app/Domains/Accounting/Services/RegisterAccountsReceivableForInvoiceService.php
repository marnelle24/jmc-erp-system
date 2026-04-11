<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Models\AccountsReceivable;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use InvalidArgumentException;

class RegisterAccountsReceivableForInvoiceService
{
    public function execute(SalesInvoice $invoice): AccountsReceivable
    {
        $invoice->loadMissing(['lines', 'salesOrder']);

        if ($invoice->accountsReceivable()->exists()) {
            throw new InvalidArgumentException(__('Accounts receivable already exists for this invoice.'));
        }

        $total = '0';
        /** @var SalesInvoiceLine $line */
        foreach ($invoice->lines as $line) {
            $unit = $line->unit_price !== null ? (string) $line->unit_price : '0';
            $lineTotal = bcmul((string) $line->quantity_invoiced, $unit, 4);
            $total = bcadd($total, $lineTotal, 4);
        }

        $postedAt = $invoice->issued_at ?? $invoice->created_at;

        return AccountsReceivable::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $invoice->salesOrder->customer_id,
            'total_amount' => $total,
            'amount_paid' => '0',
            'status' => OpenItemStatusResolver::fromAmounts($total, '0'),
            'posted_at' => $postedAt?->toDateTimeString() ?? now()->toDateTimeString(),
        ]);
    }
}
