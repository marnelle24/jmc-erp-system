<?php

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Support\GoodsReceiptRegisterFilter;
use App\Models\GoodsReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListGoodsReceiptsForTenantService
{
    /**
     * @param  array{supplier_id?: string|int|null, status?: string|null, received_from?: string|null, received_to?: string|null, po_reference?: string|null}  $filters
     */
    public function paginate(int $tenantId, int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = GoodsReceipt::query()
            ->where('tenant_id', $tenantId);

        GoodsReceiptRegisterFilter::apply($query, $filters);

        return $query
            ->with(['purchaseOrder.supplier', 'accountsPayable'])
            ->withCount('lines')
            ->latest('received_at')
            ->latest('id')
            ->paginate($perPage);
    }
}
