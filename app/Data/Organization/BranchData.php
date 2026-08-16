<?php

namespace App\Data\Organization;

use App\Models\Organization\Branch;
use Spatie\LaravelData\Data;

class BranchData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $address,
        public ?string $phone,
        public bool $is_active,
    ) {}

    public static function fromModel(Branch $branch): self
    {
        return new self(
            id: $branch->id,
            name: $branch->name,
            address: $branch->address,
            phone: $branch->phone,
            is_active: $branch->is_active,
        );
    }
}
