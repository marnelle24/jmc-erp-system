<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';

    /** Flux badge color token for ledger UI. */
    public function fluxBadgeColor(): string
    {
        return match ($this) {
            self::Receipt => 'emerald',
            self::Issue => 'rose',
            self::Adjustment => 'amber',
            self::Transfer => 'violet',
        };
    }
}
