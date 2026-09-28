<?php

namespace App\Enums\Sales;

use App\Enums\Customers\CustomerTaxCondition;

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

    public static function forTaxCondition(CustomerTaxCondition $taxCondition): self
    {
        return $taxCondition === CustomerTaxCondition::ResponsibleInscripto ? self::A : self::B;
    }
}
