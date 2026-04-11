<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_invoice_id',
        'sales_order_line_id',
        'quantity_invoiced',
        'unit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_invoiced' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}
