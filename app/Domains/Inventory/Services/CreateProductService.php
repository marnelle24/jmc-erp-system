<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;

class CreateProductService
{
    public function __construct(
        private SyncProductCategoriesService $syncCategories,
    ) {}

    /**
     * @param  array{name: string, sku?: string|null, description?: string|null, category_ids?: list<int>|null, new_categories_input?: string|null}  $data
     */
    public function execute(int $tenantId, array $data): Product
    {
        $sku = $data['sku'] ?? null;
        if ($sku === '') {
            $sku = null;
        }

        $product = Product::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'sku' => $sku,
            'description' => $data['description'] ?? null,
        ]);

        $categoryIds = $data['category_ids'] ?? [];
        $newNames = $this->syncCategories->parseCommaSeparatedNames($data['new_categories_input'] ?? null);

        if ($categoryIds !== [] || $newNames !== []) {
            $this->syncCategories->execute($tenantId, $product, $categoryIds, $newNames);
        }

        return $product->fresh(['categories']);
    }
}
