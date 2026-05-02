<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && session('current_tenant_id') !== null
            && $this->user()->tenants()->whereKey((int) session('current_tenant_id'))->exists();
    }

    /**
     * Rules for the products index Livewire form (name, description, categories only).
     * Reorder fields are not present on that page; validating them would error in Livewire.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public function rulesForProductsIndex(): array
    {
        $tenantId = (int) session('current_tenant_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'new_categories_input' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return array_merge($this->rulesForProductsIndex(), [
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
