<?php

namespace App\Enums;

enum SupplierPaymentRunStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Approved => __('Approved'),
            self::Processing => __('Processing'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
