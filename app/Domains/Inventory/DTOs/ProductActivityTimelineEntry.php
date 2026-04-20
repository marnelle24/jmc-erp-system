<?php

namespace App\Domains\Inventory\DTOs;

readonly class ProductActivityTimelineEntry
{
    /**
     * @param  'rfq'|'purchase_order'|'goods_receipt'|'inventory_movement'|'sales_order'|'sales_shipment'|'sales_invoice'  $category
     */
    public function __construct(
        public string $occurredAtIso,
        public string $category,
        public string $title,
        public ?string $subtitle,
        public ?string $url,
        public ?string $quantity,
        public ?string $amount,
        public int $tiebreaker,
    ) {}
}
