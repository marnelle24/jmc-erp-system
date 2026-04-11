<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';
}
