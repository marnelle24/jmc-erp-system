<?php

namespace App\Domains\Procurement\Support;

use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsReceipt;
use Illuminate\Database\Eloquent\Builder;

final class GoodsReceiptRegisterFilter
{
    /**
     * Apply list/register filters. The query must already be constrained to the tenant.
     *
     * @param  Builder<GoodsReceipt>  $query
     * @param  array{supplier_id?: string|int|null, status?: string|null, received_from?: string|null, received_to?: string|null, po_reference?: string|null}  $filters
     */
    public static function apply(Builder $query, array $filters): void
    {
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $enum = GoodsReceiptStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        $supplierId = isset($filters['supplier_id']) ? (int) $filters['supplier_id'] : 0;
        if ($supplierId > 0) {
            $query->whereHas('purchaseOrder', fn (Builder $q) => $q->where('supplier_id', $supplierId));
        }

        $from = isset($filters['received_from']) ? trim((string) $filters['received_from']) : '';
        if ($from !== '') {
            $query->whereDate('received_at', '>=', $from);
        }

        $to = isset($filters['received_to']) ? trim((string) $filters['received_to']) : '';
        if ($to !== '') {
            $query->whereDate('received_at', '<=', $to);
        }

        $poRef = isset($filters['po_reference']) ? trim((string) $filters['po_reference']) : '';
        if ($poRef !== '') {
            $like = '%'.addcslashes($poRef, '%_\\').'%';
            $query->whereHas('purchaseOrder', fn (Builder $q) => $q->where('reference_code', 'like', $like));
        }
    }
}
