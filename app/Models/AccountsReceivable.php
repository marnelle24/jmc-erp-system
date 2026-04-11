<?php

namespace App\Models;

use App\Enums\AccountingOpenItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountsReceivable extends Model
{
    protected $table = 'accounts_receivable';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sales_invoice_id',
        'customer_id',
        'total_amount',
        'amount_paid',
        'status',
        'posted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'status' => AccountingOpenItemStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CustomerPaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class, 'accounts_receivable_id');
    }
}
