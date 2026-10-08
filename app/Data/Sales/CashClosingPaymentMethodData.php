<?php

namespace App\Data\Sales;

use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Enums\Sales\PaymentMethodKind;
use Spatie\LaravelData\Data;

/**
 * A payment method the cashier declares in the closing count (HU-060), without its expected
 * amount: the count is blind, so the comparison only shows up once the session is closed.
 *
 * @phpstan-import-type ExpectedTotal from GetCashSessionExpectedTotals
 */
class CashClosingPaymentMethodData extends Data
{
    public function __construct(
        public int $payment_method_id,
        public string $payment_method_name,
        public string $kind_label,
        public bool $is_cash,
        public bool $requires_batch_reference,
    ) {}

    /**
     * @param  ExpectedTotal  $total
     */
    public static function fromExpectedTotal(array $total): self
    {
        $paymentMethod = $total['payment_method'];

        return new self(
            payment_method_id: $paymentMethod->id,
            payment_method_name: $paymentMethod->name,
            kind_label: $paymentMethod->kind->label(),
            is_cash: $paymentMethod->kind === PaymentMethodKind::Cash,
            requires_batch_reference: $paymentMethod->kind->requiresBatchReference(),
        );
    }
}
