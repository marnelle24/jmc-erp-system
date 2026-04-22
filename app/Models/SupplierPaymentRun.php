<?php

namespace App\Models;

use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPaymentRun extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'reference_code',
        'status',
        'scheduled_for',
        'payment_method',
        'proposed_amount',
        'approved_amount',
        'executed_amount',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'executed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierPaymentRunStatus::class,
            'payment_method' => SupplierPaymentMethod::class,
            'scheduled_for' => 'date',
            'proposed_amount' => 'decimal:4',
            'approved_amount' => 'decimal:4',
            'executed_amount' => 'decimal:4',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<SupplierPaymentRunItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierPaymentRunItem::class);
    }
}
