<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class SyncProductCategoriesService
{
    /**
     * @param  list<int>  $categoryIds  Existing category primary keys for this tenant
     * @param  list<string>  $newCategoryNames  Names to create then attach (trimmed, non-empty)
     */
    public function execute(int $tenantId, Product $product, array $categoryIds, array $newCategoryNames): void
    {
        DB::transaction(function () use ($tenantId, $product, $categoryIds, $newCategoryNames): void {
            $ids = [];

            foreach ($categoryIds as $id) {
                $ids[] = (int) $id;
            }

            foreach ($newCategoryNames as $rawName) {
                $name = trim($rawName);
                if ($name === '') {
                    continue;
                }

                $category = ProductCategory::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $name,
                    ],
                    [],
                );

                $ids[] = $category->id;
            }

            $ids = array_values(array_unique(array_filter($ids)));

            if ($ids === []) {
                $product->categories()->sync([]);

                return;
            }

            $allowed = ProductCategory::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            $product->categories()->sync($allowed);
        });
    }

    /**
     * @return list<string>
     */
    public function parseCommaSeparatedNames(?string $input): array
    {
        if ($input === null || trim($input) === '') {
            return [];
        }

        $parts = preg_split('/[,;\n\r]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $out = [];
        foreach ($parts as $part) {
            $t = trim((string) $part);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_slice(array_unique($out), 0, 50);
    }
}
