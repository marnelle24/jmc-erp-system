<?php

namespace App\Domains\Procurement\Services;

use App\Models\Rfq;
use App\Models\Tenant;

class AllocateRfqReferenceCodeService
{
    private const PREFIX = 'RFQ';

    private const PAD_LENGTH = 6;

    /**
     * Reserve the next RFQ reference for a tenant. Must run inside a DB transaction.
     */
    public function next(int $tenantId): string
    {
        Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

        $last = Rfq::query()
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
