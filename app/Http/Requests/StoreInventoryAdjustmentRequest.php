<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && session('current_tenant_id') !== null
            && $this->user()->tenants()->whereKey((int) session('current_tenant_id'))->exists();
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        $tenantId = (int) session('current_tenant_id');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
