<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\OnlineAvailabilityStatus;

readonly class OnlineAvailabilityResult
{
    public function __construct(
        public string $availableQuantity,
        public float $numericQuantity,
        public OnlineAvailabilityStatus $status,
        public bool $isAvailable,
    ) {}
}
