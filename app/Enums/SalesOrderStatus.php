<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Confirmed = 'confirmed';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
