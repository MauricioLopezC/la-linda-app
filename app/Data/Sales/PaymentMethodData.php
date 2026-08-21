<?php

namespace App\Data\Sales;

use App\Models\Sales\PaymentMethod;
use Spatie\LaravelData\Data;

class PaymentMethodData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_enabled_online,
        public bool $is_active,
    ) {}

    public static function fromModel(PaymentMethod $paymentMethod): self
    {
        return new self(
            id: $paymentMethod->id,
            name: $paymentMethod->name,
            is_enabled_online: $paymentMethod->is_enabled_online,
            is_active: $paymentMethod->is_active,
        );
    }
}
