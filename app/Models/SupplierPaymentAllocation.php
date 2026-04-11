<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_payment_id',
        'accounts_payable_id',
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
     * @return BelongsTo<SupplierPayment, $this>
     */
    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    /**
     * @return BelongsTo<AccountsPayable, $this>
     */
    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class, 'accounts_payable_id');
    }
}
