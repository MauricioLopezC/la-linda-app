<?php

namespace App\Data\Ecommerce;

use App\Models\Organization\Branch;
use Spatie\LaravelData\Data;

/**
 * A branch the customer can choose to pick up an online order.
 */
class PickupBranchOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $address,
    ) {}

    public static function fromModel(Branch $branch): self
    {
        return new self(
            id: $branch->id,
            name: $branch->name,
            address: $branch->address,
        );
    }
}
