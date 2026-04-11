<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
