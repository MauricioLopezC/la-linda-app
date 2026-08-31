<?php

namespace Database\Seeders\Purchasing;

use App\Enums\Purchasing\SupplierTaxCondition;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'business_name' => 'Molinos Río de la Plata S.A.',
                'tax_id' => '30500858628',
                'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
                'address' => 'Av. Leandro N. Alem 1110, CABA',
                'rubro' => 'Alimentos secos',
                'bank_account' => 'CBU: 0170099920000001234567 / Alias: MOLINOS.PAGOS',
                'commercial_terms' => 'Pago a 30 días fecha factura. Descuento 3% por pronto pago dentro de los 10 días.',
                'is_active' => true,
            ],
            [
                'business_name' => 'Arcor S.A.I.C.',
                'tax_id' => '30502793175',
                'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
                'address' => 'Av. Fulvio Salvador Pagani 487, Arroyito, Córdoba',
                'rubro' => 'Golosinas y conservas',
                'bank_account' => 'CBU: 0110056720000009876543 / Alias: ARCOR.PROV',
                'commercial_terms' => 'Pago a 45 días fecha factura. Pedido mínimo $500.000.',
                'is_active' => true,
            ],
            [
                'business_name' => 'Mastellone Hermanos S.A.',
                'tax_id' => '30500511849',
                'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
                'address' => 'Encarnación 1050, General Rodríguez, Buenos Aires',
                'rubro' => 'Lácteos',
                'bank_account' => 'CBU: 0720123420000004567890 / Alias: LA.SERENISIMA',
                'commercial_terms' => 'Pago semanal a 14 días. Entrega diaria en sucursales.',
                'is_active' => true,
            ],
            [
                'business_name' => 'Cervecería y Maltería Quilmes S.A.I.C.A. y G.',
                'tax_id' => '30500949461',
                'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
                'address' => 'Av. 12 de Octubre y Gran Canaria, Quilmes, Buenos Aires',
                'rubro' => 'Bebidas',
                'bank_account' => 'CBU: 0140023420000001122334 / Alias: QUILMES.VENTAS',
                'commercial_terms' => 'Pago a 30 días fecha factura. Bonificación por volumen trimestral.',
                'is_active' => true,
            ],
            [
                'business_name' => 'Distribuidora Mayorista San Cayetano',
                'tax_id' => '20289456121',
                'tax_condition' => SupplierTaxCondition::Monotributo,
                'address' => 'Calle 14 N° 456, La Plata, Buenos Aires',
                'rubro' => 'Limpieza',
                'bank_account' => 'CBU: 0140999920000007788990 / Alias: DISTRI.SANCAYETANO',
                'commercial_terms' => 'Transferencia contra entrega de mercadería.',
                'is_active' => true,
            ],
            [
                'business_name' => 'Frigorífico Las Heras S.R.L.',
                'tax_id' => '30708945123',
                'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
                'address' => 'Ruta Provincial 6 Km 120, General Las Heras, Buenos Aires',
                'rubro' => 'Carnes y embutidos',
                'bank_account' => 'CBU: 0170011120000005544332 / Alias: FRIGO.LASHERAS',
                'commercial_terms' => 'Pago a 7 días fecha entrega. Facturación con remito pesado en balanza.',
                'is_active' => true,
            ],
        ];

        Supplier::unguarded(function () use ($suppliers): void {
            foreach ($suppliers as $data) {
                Supplier::updateOrCreate(
                    ['tax_id' => $data['tax_id']],
                    [
                        ...$data,
                        'business_name_normalized' => Supplier::normalizeUniqueValue($data['business_name']),
                    ]
                );
            }
        });
    }
}
