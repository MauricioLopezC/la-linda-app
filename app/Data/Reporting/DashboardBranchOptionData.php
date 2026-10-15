<?php

namespace App\Data\Reporting;

use App\Models\Organization\Branch;
use Spatie\LaravelData\Data;

class DashboardBranchOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public static function fromModel(Branch $branch): self
    {
        return new self(
            id: $branch->id,
            name: $branch->name,
        );
    }
}
