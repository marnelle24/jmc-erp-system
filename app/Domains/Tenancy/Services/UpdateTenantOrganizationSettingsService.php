<?php

namespace App\Domains\Tenancy\Services;

use App\Models\Tenant;

class UpdateTenantOrganizationSettingsService
{
    /**
     * @param  array{base_currency: string}  $data
     */
    public function execute(Tenant $tenant, array $data): Tenant
    {
        $tenant->update([
            'base_currency' => strtoupper($data['base_currency']),
        ]);

        return $tenant->fresh();
    }
}
