<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PaymentOrderMethod;
use Spatie\LaravelData\Data;

class PaymentOrderMethodData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $payment_method_id,
        public readonly string $payment_method_name,
        public readonly string $amount,
        public readonly ?string $reference = null,
        public readonly ?string $source_account = null,
        public readonly ?string $transaction_number = null,
        public readonly ?string $check_number = null,
        public readonly ?string $check_due_date = null,
    ) {}

    public static function fromModel(PaymentOrderMethod $method): self
    {
        return new self(
            id: $method->id,
            payment_method_id: $method->payment_method_id,
            payment_method_name: $method->paymentMethod->name,
            amount: $method->amount,
            reference: $method->reference,
            source_account: $method->source_account,
            transaction_number: $method->transaction_number,
            check_number: $method->check_number,
            check_due_date: $method->check_due_date?->format('Y-m-d'),
        );
    }
}
