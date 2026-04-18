<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::OnHold => __('On hold'),
            self::Blocked => __('Blocked'),
        };
    }
}
