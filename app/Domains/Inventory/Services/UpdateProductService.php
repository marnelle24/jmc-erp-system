<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;

class UpdateProductService
{
    public function __construct(
        private SyncProductCategoriesService $syncCategories,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, reorder_point?: string|null, reorder_qty?: string|null, category_ids?: list<int>|null, new_categories_input?: string|null}  $data
     */
    public function execute(Product $product, array $data): Product
    {
        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ];

        if (array_key_exists('reorder_point', $data)) {
            $payload['reorder_point'] = $data['reorder_point'];
        }

        if (array_key_exists('reorder_qty', $data)) {
            $payload['reorder_qty'] = $data['reorder_qty'];
        }

        $product->update($payload);

        if (array_key_exists('category_ids', $data) || array_key_exists('new_categories_input', $data)) {
            $categoryIds = $data['category_ids'] ?? [];
            $newNames = $this->syncCategories->parseCommaSeparatedNames($data['new_categories_input'] ?? null);
            $this->syncCategories->execute((int) $product->tenant_id, $product->fresh(), $categoryIds, $newNames);
        }

        return $product->fresh(['categories']);
    }
}
