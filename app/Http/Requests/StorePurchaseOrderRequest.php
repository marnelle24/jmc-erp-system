<?php

namespace App\Http\Requests;

use App\Domains\Procurement\Validation\PurchaseOrderStoreRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && session('current_tenant_id') !== null
            && $this->user()->tenants()->whereKey((int) session('current_tenant_id'))->exists();
    }

    protected function prepareForValidation(): void
    {
        $rawRfq = $this->input('rfq_id');
        $rfqId = ($rawRfq === null || $rawRfq === '') ? null : (int) $rawRfq;

        $lines = $this->input('lines');
        if (is_array($lines)) {
            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $rl = $line['rfq_line_id'] ?? null;
                $lines[$i]['rfq_line_id'] = ($rl === null || $rl === '') ? null : (int) $rl;
            }
            $this->merge(['lines' => $lines]);
        }

        $this->merge(['rfq_id' => $rfqId]);
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        $tenantId = (int) session('current_tenant_id');

        return PurchaseOrderStoreRules::rules($tenantId);
    }

    public function withValidator(Validator $validator): void
    {
        $tenantId = (int) session('current_tenant_id');
        PurchaseOrderStoreRules::withValidatorAfter($validator, $tenantId);
    }
}
