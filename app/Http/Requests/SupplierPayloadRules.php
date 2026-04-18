<?php

namespace App\Http\Requests;

use App\Enums\SupplierStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class SupplierPayloadRules
{
    /**
     * @param  ?int  $ignoreSupplierId  When updating, pass the supplier id so `code` can stay unchanged.
     * @return array<string, list<string|ValidationRule>>
     */
    public static function rules(?int $ignoreSupplierId = null): array
    {
        $tenantId = (int) session('current_tenant_id');

        $uniqueCode = Rule::unique('suppliers', 'code')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if ($ignoreSupplierId !== null) {
            $uniqueCode = $uniqueCode->ignore($ignoreSupplierId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', $uniqueCode],
            'status' => ['required', Rule::enum(SupplierStatus::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:65535'],
            'payment_terms' => ['nullable', 'string', 'max:128'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
