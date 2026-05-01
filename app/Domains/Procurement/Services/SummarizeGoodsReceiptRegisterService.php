<?php

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Support\GoodsReceiptLineUnitCost;
use App\Domains\Procurement\Support\GoodsReceiptRegisterFilter;
use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsReceipt;

class SummarizeGoodsReceiptRegisterService
{
    /**
     * @param  array{supplier_id?: string|int|null, status?: string|null, received_from?: string|null, received_to?: string|null, po_reference?: string|null}  $filters
     * @return array{receipt_count: int, awaiting_accounts_payable: int, extended_value: string}
     */
    public function execute(int $tenantId, array $filters = []): array
    {
        $base = GoodsReceipt::query()->where('tenant_id', $tenantId);
        GoodsReceiptRegisterFilter::apply($base, $filters);

        $receiptCount = (clone $base)->count();

        $awaitingAccountsPayable = (clone $base)
            ->where('status', GoodsReceiptStatus::Posted)
            ->whereDoesntHave('accountsPayable')
            ->count();

        $extended = '0';
        (clone $base)->with(['lines.purchaseOrderLine'])->chunkById(100, function ($receipts) use (&$extended): void {
            foreach ($receipts as $receipt) {
                foreach ($receipt->lines as $line) {
                    $extended = bcadd($extended, GoodsReceiptLineUnitCost::extendedValue($line), 4);
                }
            }
        });

        return [
            'receipt_count' => $receiptCount,
            'awaiting_accounts_payable' => $awaitingAccountsPayable,
            'extended_value' => $extended,
        ];
    }
}
