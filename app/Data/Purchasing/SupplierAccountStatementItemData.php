<?php

namespace App\Data\Purchasing;

use Spatie\LaravelData\Data;

class SupplierAccountStatementItemData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $type_label,
        public string $formatted_number,
        public string $issue_date,
        public string $issue_date_formatted,
        public ?string $due_date,
        public ?string $due_date_formatted,
        public string $total_amount,
        public string $paid_amount,
        public string $balance,
        public int $aging_days,
        public bool $is_overdue,
    ) {}
}
