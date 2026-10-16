<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\OnlineAvailabilityStatus;

readonly class OnlineAvailabilityResult
{
    /** @param numeric-string $availableQuantity */
    public function __construct(
        public string $availableQuantity,
        public OnlineAvailabilityStatus $status,
        public bool $isAvailable,
    ) {}
}
