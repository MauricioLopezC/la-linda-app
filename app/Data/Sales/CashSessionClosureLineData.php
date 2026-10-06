<?php

namespace App\Data\Sales;

use App\Models\Sales\CashSessionClosureLine;
use Spatie\LaravelData\Data;

/**
 * Expected vs. declared of one payment method in a closed session (HU-060).
 */
class CashSessionClosureLineData extends Data
{
    public function __construct(
        public int $payment_method_id,
        public string $payment_method_name,
        public string $kind,
        public string $kind_label,
        public string $expected_amount,
        public string $declared_amount,
        public string $difference,
        public ?string $batch_reference,
    ) {}

    public static function fromModel(CashSessionClosureLine $line): self
    {
        return new self(
            payment_method_id: $line->payment_method_id,
            payment_method_name: $line->paymentMethod->name,
            kind: $line->paymentMethod->kind->value,
            kind_label: $line->paymentMethod->kind->label(),
            expected_amount: $line->expected_amount,
            declared_amount: $line->declared_amount,
            difference: $line->difference,
            batch_reference: $line->batch_reference,
        );
    }
}
