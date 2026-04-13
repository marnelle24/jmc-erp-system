<?php

namespace App\Domains\Crm\Services;

use App\Models\Customer;

class UpdateCustomerService
{
    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, address?: string|null}  $data
     */
    public function execute(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return $customer->fresh();
    }
}
