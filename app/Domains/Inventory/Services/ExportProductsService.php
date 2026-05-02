<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProductsService
{
    public function download(int $tenantId, ?Carbon $at = null): StreamedResponse
    {
        $at ??= now();
        $filename = 'products-'.$at->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($tenantId): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['name', 'sku', 'description']);

            Product::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->chunk(500, function ($products) use ($out): void {
                    foreach ($products as $product) {
                        fputcsv($out, [
                            $product->name,
                            $product->sku ?? '',
                            $product->description ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
