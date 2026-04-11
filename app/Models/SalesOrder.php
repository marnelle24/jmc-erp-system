<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'status',
        'order_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'status' => SalesOrderStatus::class,
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SalesOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<SalesShipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(SalesShipment::class);
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }
}
