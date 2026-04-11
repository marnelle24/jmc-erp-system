<?php

namespace App\Enums;

enum RfqStatus: string
{
    case PendingForApproval = 'pending_for_approval';
    case Sent = 'sent';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PendingForApproval => __('Pending for approval'),
            self::Sent => __('Sent'),
            self::Closed => __('Closed'),
        };
    }
}
