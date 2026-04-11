<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => __('SKU'),
        ];
    }
}
