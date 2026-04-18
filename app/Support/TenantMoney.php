<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Number;

/**
 * Formats money using the current tenant's base currency (ISO 4217).
 */
final class TenantMoney
{
    public static function currencyCode(?int $tenantId = null): string
    {
        $tenantId ??= session('current_tenant_id');
        if ($tenantId === null) {
            return 'USD';
        }

        $code = Tenant::query()->whereKey((int) $tenantId)->value('base_currency');

        return is_string($code) && strlen($code) === 3 ? strtoupper($code) : 'USD';
    }

    /**
     * @param  float|string  $amount
     */
    public static function format($amount, ?int $tenantId = null, ?int $precision = 2): string
    {
        return Number::currency((float) $amount, in: self::currencyCode($tenantId), precision: $precision);
    }
}
