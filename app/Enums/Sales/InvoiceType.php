<?php

namespace App\Enums\Sales;

use App\Enums\Customers\CustomerTaxCondition;
use App\Models\Customers\Customer;

/**
 * Letter of the invoice. La Linda is a responsable inscripto: it issues A to other
 * responsables inscriptos and B to everyone else. It never issues C.
 */
enum InvoiceType: string
{
    case A = 'A';
    case B = 'B';

    public function label(): string
    {
        return "Factura {$this->value}";
    }

    /**
     * Voucher type code assigned by ARCA, printed under the letter (001 = Factura A, 006 = Factura B).
     */
    public function voucherCode(): string
    {
        return match ($this) {
            self::A => '001',
            self::B => '006',
        };
    }

    public static function forTaxCondition(CustomerTaxCondition $taxCondition): self
    {
        return $taxCondition === CustomerTaxCondition::ResponsibleInscripto ? self::A : self::B;
    }

    public static function forCustomer(Customer $customer): self
    {
        return self::forTaxCondition($customer->tax_condition);
    }
}
