<?php

namespace App\Enums;

enum SupplierPaymentMethod: string
{
    case Cash = 'cash';
    case Pdc = 'pdc';
    case BankTransfer = 'bank_transfer';
    case DigitalPayment = 'digital_payment';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Pdc => __('PDC'),
            self::BankTransfer => __('Bank transfer'),
            self::DigitalPayment => __('Digital payment'),
        };
    }
}
