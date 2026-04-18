<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantOrganizationSettingsRequest extends FormRequest
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
            'base_currency' => ['required', 'string', 'size:3', Rule::in(self::allowedCurrencies())],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedCurrencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'PHP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'HKD', 'SGD',
            'INR', 'KRW', 'MXN', 'BRL', 'NZD', 'SEK', 'NOK', 'DKK', 'PLN', 'THB', 'MYR',
            'IDR', 'VND', 'AED', 'SAR', 'ZAR', 'TRY', 'ILS', 'CZK', 'HUF', 'RON',
        ];
    }
}
