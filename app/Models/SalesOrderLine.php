<?php

namespace App\Models;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity_ordered',
        'unit_price',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<SalesShipmentLine, $this>
     */
    public function shipmentLines(): HasMany
    {
        return $this->hasMany(SalesShipmentLine::class);
    }

    /**
     * @return HasMany<SalesInvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function totalShippedQuantity(): string
    {
        $sum = $this->shipmentLines()
            ->whereHas('salesShipment', fn ($q) => $q->where('status', SalesShipmentStatus::Posted))
            ->sum('quantity_shipped');

        return $sum === null ? '0' : (string) $sum;
    }

    public function totalInvoicedQuantity(): string
    {
        $sum = $this->invoiceLines()
            ->whereHas('salesInvoice', fn ($q) => $q->where('status', SalesInvoiceStatus::Issued))
            ->sum('quantity_invoiced');

        return $sum === null ? '0' : (string) $sum;
    }
}
