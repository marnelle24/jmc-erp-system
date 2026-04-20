<?php

namespace App\Domains\Inventory\DTOs;

readonly class ProductStockKpis
{
    public function __construct(
        public string $onHand,
        public string $incoming,
        public string $committed,
        public ?string $lastMovementAtIso,
        public ?string $lastReceiptAtIso,
        public ?string $lastShipmentAtIso,
    ) {}
}
