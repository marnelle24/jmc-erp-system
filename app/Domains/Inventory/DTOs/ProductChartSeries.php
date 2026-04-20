<?php

namespace App\Domains\Inventory\DTOs;

readonly class ProductChartSeries
{
    /**
     * @param  list<array{t: string, y: float}>  $inventoryBalance
     * @param  list<array{t: string, y: float}>  $purchaseUnitCost
     * @param  list<array{t: string, y: float}>  $saleUnitPrice
     */
    public function __construct(
        public array $inventoryBalance,
        public array $purchaseUnitCost,
        public array $saleUnitPrice,
    ) {}
}
