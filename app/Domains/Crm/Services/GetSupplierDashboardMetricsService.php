<?php

namespace App\Domains\Crm\Services;

use App\Enums\AccountingOpenItemStatus;
use App\Models\AccountsPayable;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\Carbon;

class GetSupplierDashboardMetricsService
{
    /**
     * Open AP and aging use `posted_at` as the reference date (no separate due date on payables).
     *
     * @return array{
     *     open_ap_balance: string,
     *     aging_0_30: string,
     *     aging_31_60: string,
     *     aging_61_90: string,
     *     aging_over_90: string,
     *     ytd_spend_posted: string,
     *     ytd_po_value: string,
     *     last_po_date: ?Carbon,
     *     last_payment_at: ?Carbon,
     * }
     */
    public function execute(int $tenantId, Supplier $supplier): array
    {
        $openPayables = AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->whereNotNull('posted_at')
            ->get(['total_amount', 'amount_paid', 'posted_at']);

        $openApBalance = '0';
        $aging030 = '0';
        $aging3160 = '0';
        $aging6190 = '0';
        $aging90 = '0';
        $today = Carbon::now()->startOfDay();

        foreach ($openPayables as $payable) {
            $balance = bcsub((string) $payable->total_amount, (string) $payable->amount_paid, 4);
            if (bccomp($balance, '0', 4) !== 1) {
                continue;
            }
            $openApBalance = bcadd($openApBalance, $balance, 4);

            $posted = Carbon::parse($payable->posted_at)->startOfDay();
            $days = max(0, (int) $posted->diffInDays($today));

            if ($days <= 30) {
                $aging030 = bcadd($aging030, $balance, 4);
            } elseif ($days <= 60) {
                $aging3160 = bcadd($aging3160, $balance, 4);
            } elseif ($days <= 90) {
                $aging6190 = bcadd($aging6190, $balance, 4);
            } else {
                $aging90 = bcadd($aging90, $balance, 4);
            }
        }

        $yearStart = Carbon::now()->startOfYear();
        $yearEnd = Carbon::now()->endOfYear();

        $ytdSpendPosted = (string) (AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $supplier->id)
            ->whereBetween('posted_at', [$yearStart, $yearEnd])
            ->sum('total_amount') ?? '0');

        $ytdPoValue = (string) PurchaseOrderLine::query()
            ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.tenant_id', $tenantId)
            ->where('purchase_orders.supplier_id', $supplier->id)
            ->whereBetween('purchase_orders.order_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->selectRaw(
                'SUM(purchase_order_lines.quantity_ordered * COALESCE(purchase_order_lines.unit_cost, 0)) as v'
            )
            ->value('v') ?? '0';

        $lastPoDate = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $supplier->id)
            ->max('order_date');

        $lastPaymentAt = SupplierPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', $supplier->id)
            ->max('paid_at');

        return [
            'open_ap_balance' => $openApBalance,
            'aging_0_30' => $aging030,
            'aging_31_60' => $aging3160,
            'aging_61_90' => $aging6190,
            'aging_over_90' => $aging90,
            'ytd_spend_posted' => $ytdSpendPosted,
            'ytd_po_value' => $ytdPoValue,
            'last_po_date' => $lastPoDate !== null ? Carbon::parse($lastPoDate) : null,
            'last_payment_at' => $lastPaymentAt !== null ? Carbon::parse($lastPaymentAt) : null,
        ];
    }
}
