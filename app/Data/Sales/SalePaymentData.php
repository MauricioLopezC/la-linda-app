<?php

namespace App\Data\Sales;

use App\Models\Sales\CashMovement;
use Spatie\LaravelData\Data;

class SalePaymentData extends Data
{
    public function __construct(
        public int $id,
        public int $payment_method_id,
        public string $payment_method_name,
        public string $payment_method_kind,
        public string $payment_method_kind_label,
        public string $amount,
        public ?string $tendered_amount,
        public ?string $change_amount,
        public string $created_at,
        public string $created_at_formatted,
    ) {}

    public static function fromModel(CashMovement $movement): self
    {
        $movement->loadMissing('paymentMethod');

        $changeAmount = null;
        if ($movement->tendered_amount !== null) {
            $changeCents = (int) round(((float) $movement->tendered_amount - (float) $movement->amount) * 100);
            $changeAmount = number_format(max(0, $changeCents) / 100, 2, '.', '');
        }

        return new self(
            id: $movement->id,
            payment_method_id: $movement->payment_method_id,
            payment_method_name: $movement->paymentMethod->name,
            payment_method_kind: $movement->paymentMethod->kind->value,
            payment_method_kind_label: $movement->paymentMethod->kind->label(),
            amount: $movement->amount,
            tendered_amount: $movement->tendered_amount,
            change_amount: $changeAmount,
            created_at: $movement->created_at?->toIso8601String() ?? '',
            created_at_formatted: $movement->created_at?->format('d/m/Y H:i') ?? '',
        );
    }
}
