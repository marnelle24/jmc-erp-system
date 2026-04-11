<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesShipmentLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_shipment_id',
        'sales_order_line_id',
        'quantity_shipped',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_shipped' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesShipment, $this>
     */
    public function salesShipment(): BelongsTo
    {
        return $this->belongsTo(SalesShipment::class);
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}
