<?php

namespace App\Data\Sales;

use App\Models\Sales\PaymentMethod;
use Spatie\LaravelData\Data;

class PaymentMethodData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $kind,
        public string $kind_label,
        public bool $is_enabled_online,
        public bool $is_active,
    ) {}

    public static function fromModel(PaymentMethod $paymentMethod): self
    {
        return new self(
            id: $paymentMethod->id,
            name: $paymentMethod->name,
            kind: $paymentMethod->kind->value,
            kind_label: $paymentMethod->kind->label(),
            is_enabled_online: $paymentMethod->is_enabled_online,
            is_active: $paymentMethod->is_active,
        );
    }
}
