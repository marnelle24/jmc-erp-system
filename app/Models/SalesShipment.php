<?php

namespace App\Models;

use App\Enums\SalesShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesShipment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'status',
        'shipped_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'status' => SalesShipmentStatus::class,
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
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return HasMany<SalesShipmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesShipmentLine::class);
    }
}
