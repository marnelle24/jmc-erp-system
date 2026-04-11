<?php

namespace App\Enums;

enum AccountingOpenItemStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
}
