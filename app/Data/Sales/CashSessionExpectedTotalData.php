<?php

namespace App\Data\Sales;

use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Enums\Sales\PaymentMethodKind;
use Spatie\LaravelData\Data;

/**
 * What one payment method should hold at the end of a session (HU-060).
 *
 * @phpstan-import-type ExpectedTotal from GetCashSessionExpectedTotals
 */
class CashSessionExpectedTotalData extends Data
{
    public function __construct(
        public int $payment_method_id,
        public string $payment_method_name,
        public string $kind,
        public string $kind_label,
        public bool $is_cash,
        public bool $requires_batch_reference,
        public string $opening_amount,
        public string $sales_amount,
        public string $income_amount,
        public string $expense_amount,
        public string $expected_amount,
    ) {}

    /**
     * @param  ExpectedTotal  $total
     */
    public static function fromArray(array $total): self
    {
        $paymentMethod = $total['payment_method'];

        return new self(
            payment_method_id: $paymentMethod->id,
            payment_method_name: $paymentMethod->name,
            kind: $paymentMethod->kind->value,
            kind_label: $paymentMethod->kind->label(),
            is_cash: $paymentMethod->kind === PaymentMethodKind::Cash,
            requires_batch_reference: $paymentMethod->kind->requiresBatchReference(),
            opening_amount: $total['opening_amount'],
            sales_amount: $total['sales_amount'],
            income_amount: $total['income_amount'],
            expense_amount: $total['expense_amount'],
            expected_amount: $total['expected_amount'],
        );
    }
}
