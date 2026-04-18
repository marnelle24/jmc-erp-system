<?php

namespace App\Domains\Crm\Services;

use App\Models\Supplier;

class UpdateSupplierService
{
    /**
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     status?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     payment_terms?: string|null,
     *     tax_id?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name' => $data['name'],
            'code' => self::emptyToNull($data['code'] ?? null),
            'status' => $data['status'] ?? $supplier->status->value,
            'email' => self::emptyToNull($data['email'] ?? null),
            'phone' => self::emptyToNull($data['phone'] ?? null),
            'address' => self::emptyToNull($data['address'] ?? null),
            'payment_terms' => self::emptyToNull($data['payment_terms'] ?? null),
            'tax_id' => self::emptyToNull($data['tax_id'] ?? null),
            'notes' => self::emptyToNull($data['notes'] ?? null),
        ]);

        return $supplier->fresh();
    }

    private static function emptyToNull(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
