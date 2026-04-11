<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) session('current_tenant_id');

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId),
            ],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.accounts_payable_id' => [
                'required',
                'integer',
                Rule::exists('accounts_payable', 'id')->where('tenant_id', $tenantId),
            ],
            'allocations.*.amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];
    }
}
