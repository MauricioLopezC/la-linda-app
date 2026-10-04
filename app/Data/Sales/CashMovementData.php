<?php

namespace App\Data\Sales;

use App\Models\Sales\CashMovement;
use Spatie\LaravelData\Data;

class CashMovementData extends Data
{
    public function __construct(
        public int $id,
        public int $cash_session_id,
        public string $type,
        public string $type_label,
        public int $sign,
        public int $payment_method_id,
        public string $payment_method_name,
        public string $amount,
        public ?int $sale_id,
        public ?string $reason,
        public int $user_id,
        public string $user_name,
        public string $created_at,
        public string $created_at_formatted,
    ) {}

    public static function fromModel(CashMovement $movement): self
    {
        return new self(
            id: $movement->id,
            cash_session_id: $movement->cash_session_id,
            type: $movement->type->value,
            type_label: $movement->type->label(),
            sign: $movement->type->sign(),
            payment_method_id: $movement->payment_method_id,
            payment_method_name: $movement->paymentMethod->name,
            amount: $movement->amount,
            sale_id: $movement->sale_id,
            reason: $movement->reason,
            user_id: $movement->user_id,
            user_name: $movement->user->name,
            created_at: $movement->created_at?->toIso8601String() ?? '',
            created_at_formatted: $movement->created_at?->format('d/m/Y H:i') ?? '',
        );
    }
}
