<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesShipmentRequest extends FormRequest
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
        return [
            'shipped_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'integer'],
            'lines.*.quantity_shipped' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
