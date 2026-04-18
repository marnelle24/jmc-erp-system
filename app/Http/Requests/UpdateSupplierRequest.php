<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
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
        $supplier = $this->route('supplier');

        return SupplierPayloadRules::rules($supplier instanceof Supplier ? $supplier->id : null);
    }
}
