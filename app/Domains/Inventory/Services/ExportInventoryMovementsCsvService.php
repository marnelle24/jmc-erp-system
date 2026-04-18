<?php

namespace App\Domains\Inventory\Services;

use App\Enums\InventoryMovementType;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportInventoryMovementsCsvService
{
    public function __construct(
        private readonly ListInventoryMovementsForTenantService $listMovements,
        private readonly ResolveInventoryMovementSourceLinkService $resolveSource,
    ) {}

    /**
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     movement_type?: string|null,
     *     product_search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null
     * }  $filters
     */
    public function download(int $tenantId, array $filters): StreamedResponse
    {
        $query = $this->listMovements->query($tenantId, $filters);
        $filename = 'inventory-movements-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('When'),
                __('Product'),
                __('SKU'),
                __('Type'),
                __('Quantity'),
                __('Notes'),
                __('Source'),
                __('Source URL'),
            ]);

            foreach ($query->lazy(500) as $movement) {
                $source = $this->resolveSource->resolve($movement);
                $product = $movement->product;

                fputcsv($handle, [
                    $movement->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    $product !== null ? $product->name : '',
                    $product !== null ? (string) ($product->sku ?? '') : '',
                    $movement->movement_type instanceof InventoryMovementType
                        ? $movement->movement_type->value
                        : (string) $movement->movement_type,
                    (string) $movement->quantity,
                    (string) ($movement->notes ?? ''),
                    $source->label,
                    $source->url ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
