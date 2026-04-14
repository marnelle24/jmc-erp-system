<?php

namespace App\Enums;

enum RfqLineUnitType: string
{
    case Piece = 'piece';
    case Sheet = 'sheet';
    case Dozen = 'dozen';
    case Kilo = 'kilo';
    case Bundle = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::Piece => __('Piece'),
            self::Sheet => __('Sheet'),
            self::Dozen => __('Dozen'),
            self::Kilo => __('Kilo'),
            self::Bundle => __('Bundle'),
        };
    }

    public static function default(): self
    {
        return self::Piece;
    }
}
