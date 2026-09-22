<?php

namespace App\Enums\Purchasing;

enum SupplierVoucherLetter: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case M = 'M';
    case R = 'R';
    case X = 'X';

    public function discriminatesVat(): bool
    {
        return $this === self::A || $this === self::M;
    }

    public function label(): string
    {
        return $this->value;
    }

    /** @return list<self> */
    public static function forVoucherType(SupplierVoucherType|string|null $type): array
    {
        $voucherType = is_string($type) ? SupplierVoucherType::tryFrom($type) : $type;

        if ($voucherType === SupplierVoucherType::Remito) {
            return [self::R, self::X];
        }

        return [self::A, self::B, self::C, self::M];
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function toOptions(SupplierVoucherType|string|null $type = null): array
    {
        $cases = $type !== null ? self::forVoucherType($type) : self::cases();

        return array_map(
            fn (self $letter): array => ['value' => $letter->value, 'label' => $letter->label()],
            $cases
        );
    }
}
