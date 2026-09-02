<?php

namespace App\Enums\Purchasing;

enum SupplierVoucherLetter: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case M = 'M';

    public function discriminatesVat(): bool
    {
        return $this === self::A || $this === self::M;
    }

    public function label(): string
    {
        return $this->value;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $letter): array => ['value' => $letter->value, 'label' => $letter->label()],
            self::cases()
        );
    }
}
