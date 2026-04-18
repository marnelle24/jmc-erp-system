<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'status',
        'email',
        'phone',
        'address',
        'payment_terms',
        'tax_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
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
     * @return HasMany<Rfq, $this>
     */
    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasManyThrough<GoodsReceipt, PurchaseOrder, $this>
     */
    public function goodsReceipts(): HasManyThrough
    {
        return $this->hasManyThrough(GoodsReceipt::class, PurchaseOrder::class);
    }

    /**
     * @return HasMany<AccountsPayable, $this>
     */
    public function accountsPayables(): HasMany
    {
        return $this->hasMany(AccountsPayable::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
