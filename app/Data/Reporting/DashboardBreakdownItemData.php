<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * One row of a dashboard breakdown (by channel, branch or payment method), shared by every section.
 */
class DashboardBreakdownItemData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $amount,
        public int $count,
    ) {}
}
