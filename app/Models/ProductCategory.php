<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductCategory $category): void {
            if ($category->getAttribute('tenant_id') !== null) {
                return;
            }

            $sessionTenant = session('current_tenant_id');
            $category->tenant_id = is_numeric($sessionTenant)
                ? (int) $sessionTenant
                : 1;
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withTimestamps();
    }
}
