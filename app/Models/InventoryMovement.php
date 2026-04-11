<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InventoryMovement $movement): void {
            if ($movement->tenant_id === null && $movement->product_id !== null) {
                $movement->tenant_id = Product::query()
                    ->whereKey($movement->product_id)
                    ->value('tenant_id');
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'quantity',
        'movement_type',
        'notes',
        'reference_type',
        'reference_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'movement_type' => InventoryMovementType::class,
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
