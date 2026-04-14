<?php

namespace App\Models;

use App\Enums\RfqLineUnitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'rfq_id',
        'product_id',
        'quantity',
        'unit_type',
        'unit_price',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_type' => RfqLineUnitType::class,
            'unit_price' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
