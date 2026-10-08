<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Issuer Information (Supermercados La Linda)
    |--------------------------------------------------------------------------
    |
    | Fiscal data of Supermercados La Linda for sales invoicing and vouchers
    | (HU-042 / HU-043).
    |
    */

    'issuer' => [
        'name' => env('INVOICING_ISSUER_NAME', 'Supermercados La Linda S.A.'),
        'cuit' => env('INVOICING_ISSUER_CUIT', '30-71654321-4'),
        'address' => env('INVOICING_ISSUER_ADDRESS', 'Av. Paraguay 2150, Salta Capital, Salta'),
        'tax_condition' => env('INVOICING_ISSUER_TAX_CONDITION', 'IVA Responsable Inscripto'),
        'activity_start_date' => env('INVOICING_ISSUER_ACTIVITY_START', '01/01/2010'),
        'gross_income' => env('INVOICING_ISSUER_GROSS_INCOME', '30-71654321-4'),
    ],

];
