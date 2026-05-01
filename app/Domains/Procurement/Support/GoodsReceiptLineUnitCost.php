<?php

namespace App\Domains\Procurement\Support;

use App\Models\GoodsReceiptLine;

final class GoodsReceiptLineUnitCost
{
    public static function resolvedUnitCost(GoodsReceiptLine $line): string
    {
        if ($line->unit_cost !== null) {
            return (string) $line->unit_cost;
        }

        $poLine = $line->purchaseOrderLine;
        if ($poLine !== null && $poLine->unit_cost !== null) {
            return (string) $poLine->unit_cost;
        }

        return '0';
    }

    public static function extendedValue(GoodsReceiptLine $line): string
    {
        $unit = self::resolvedUnitCost($line);

        return bcmul((string) $line->quantity_received, $unit, 4);
    }
}
