<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

class DashboardEvolutionData extends Data
{
    /** @param array<int, DashboardEvolutionPointData> $series */
    public function __construct(
        public string $net_result,
        public ?float $net_margin_pct,
        public ?float $net_result_variation_pct,
        public array $series,
    ) {}
}
