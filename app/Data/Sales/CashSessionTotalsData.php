<?php

namespace App\Data\Sales;

use Spatie\LaravelData\Data;

class CashSessionTotalsData extends Data
{
    public function __construct(
        public string $opening_amount,
        public string $sales_cash_amount,
        public string $income_amount,
        public string $expense_amount,
        public string $expected_cash,
    ) {}

    /**
     * @param  array{opening_amount: string, sales_cash_amount: string, income_amount: string, expense_amount: string, expected_cash: string}  $summary
     */
    public static function fromArray(array $summary): self
    {
        return new self(
            opening_amount: $summary['opening_amount'],
            sales_cash_amount: $summary['sales_cash_amount'],
            income_amount: $summary['income_amount'],
            expense_amount: $summary['expense_amount'],
            expected_cash: $summary['expected_cash'],
        );
    }
}
