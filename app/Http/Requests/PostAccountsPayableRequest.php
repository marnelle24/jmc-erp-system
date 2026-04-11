<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostAccountsPayableRequest extends FormRequest
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
            'goods_receipt_id' => [
                'required',
                'integer',
                Rule::exists('goods_receipts', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
