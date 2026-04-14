<?php

namespace App\Domains\Crm\Services;

use App\Models\Supplier;

class UpdateSupplierService
{
    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, address?: string|null}  $data
     */
    public function execute(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return $supplier->fresh();
    }
}
