<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentRunItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'supplier_payment_run_id',
        'accounts_payable_id',
        'supplier_id',
        'planned_amount',
        'executed_amount',
        'supplier_payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:4',
            'executed_amount' => 'decimal:4',
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
     * @return BelongsTo<SupplierPaymentRun, $this>
     */
    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentRun::class, 'supplier_payment_run_id');
    }

    /**
     * @return BelongsTo<AccountsPayable, $this>
     */
    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class, 'accounts_payable_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<SupplierPayment, $this>
     */
    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }
}
