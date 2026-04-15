<?php

namespace App\Domains\Procurement\Services;

use App\Models\PurchaseOrder;
use App\Models\Tenant;

class AllocatePurchaseOrderReferenceCodeService
{
    private const PREFIX = 'PO';

    private const PAD_LENGTH = 6;

    /**
     * Reserve the next PO reference for a tenant. Must run inside a DB transaction.
     */
    public function next(int $tenantId): string
    {
        Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

        $last = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_code', 'like', self::PREFIX.'%')
            ->orderByDesc('reference_code')
            ->value('reference_code');

        $nextNumber = 1;
        if ($last !== null && str_starts_with($last, self::PREFIX)) {
            $suffix = substr($last, strlen(self::PREFIX));
            if ($suffix !== '' && ctype_digit($suffix)) {
                $nextNumber = (int) $suffix + 1;
            }
        }

        return self::PREFIX.str_pad((string) $nextNumber, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }
}
