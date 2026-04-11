<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentAllocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_payment_id',
        'accounts_receivable_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    /**
     * @return BelongsTo<AccountsReceivable, $this>
     */
    public function accountsReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountsReceivable::class, 'accounts_receivable_id');
    }
}
