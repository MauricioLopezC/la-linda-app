<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * Filters applied to the whole management dashboard, already resolved to their defaults.
 */
class DashboardFiltersData extends Data
{
    public function __construct(
        public string $date_from,
        public string $date_to,
        public ?int $branch_id,
        public ?string $channel,
    ) {}
}
