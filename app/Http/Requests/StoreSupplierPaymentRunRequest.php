<?php

namespace App\Http\Requests;

use App\Enums\SupplierPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRunRequest extends FormRequest
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
            'scheduled_for' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::enum(SupplierPaymentMethod::class)],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId),
            ],
            'due_date_to' => ['nullable', 'date'],
            'selected_payable_ids' => ['nullable', 'array'],
            'selected_payable_ids.*' => [
                'integer',
                Rule::exists('accounts_payable', 'id')->where('tenant_id', $tenantId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
