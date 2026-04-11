<?php

namespace App\Domains\Crm\Services;

use App\Models\Supplier;

class CreateSupplierService
{
    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, address?: string|null}  $data
     */
    public function execute(int $tenantId, array $data): Supplier
    {
        return Supplier::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }
}
