<?php

namespace App\Domains\Inventory\DTOs;

final readonly class InventoryMovementSourceLink
{
    public function __construct(
        public string $label,
        public ?string $url = null,
    ) {}
}
