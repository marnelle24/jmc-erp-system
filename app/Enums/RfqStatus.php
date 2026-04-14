<?php

namespace App\Enums;

enum RfqStatus: string
{
    case PendingForApproval = 'pending_for_approval';
    case ApprovedNoPo = 'approved_no_po';
    case Sent = 'sent';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PendingForApproval => __('Pending for approval'),
            self::ApprovedNoPo => __('Approved - No PO'),
            self::Sent => __('Sent'),
            self::Closed => __('Closed'),
        };
    }
}
