<?php

namespace Database\Seeders\Purchasing;

use App\Actions\Purchasing\CreateSupplierVoucher;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Seeder;

class SupplierVoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateSupplierVoucher $createSupplierVoucher): void
    {
        /** @var array<int, array{
         *     supplier_tax_id: string,
         *     type: SupplierVoucherType,
         *     point_of_sale: string,
         *     number: string,
         *     issue_date: string,
         *     due_date: ?string,
         *     net_amount: string,
         *     other_taxes_amount: string,
         *     notes: string
         * }> $vouchers
         */
        $vouchers = [
            [
                'supplier_tax_id' => '30500858628',
                'type' => SupplierVoucherType::Invoice,
                'point_of_sale' => '0001',
                'number' => '00012001',
                'issue_date' => today()->subDays(20)->toDateString(),
                'due_date' => today()->addDays(10)->toDateString(),
                'net_amount' => '100000.00',
                'other_taxes_amount' => '250.00',
                'notes' => 'Factura de demostración vigente.',
            ],
            [
                'supplier_tax_id' => '30502793175',
                'type' => SupplierVoucherType::Invoice,
                'point_of_sale' => '0001',
                'number' => '00004567',
                'issue_date' => today()->subDays(60)->toDateString(),
                'due_date' => today()->subDays(30)->toDateString(),
                'net_amount' => '50000.00',
                'other_taxes_amount' => '0.00',
                'notes' => 'Factura de demostración vencida.',
            ],
            [
                'supplier_tax_id' => '30500511849',
                'type' => SupplierVoucherType::CreditNote,
                'point_of_sale' => '0002',
                'number' => '00000321',
                'issue_date' => today()->subDays(5)->toDateString(),
                'due_date' => null,
                'net_amount' => '10000.00',
                'other_taxes_amount' => '0.00',
                'notes' => 'Nota de crédito pendiente de imputar.',
            ],
            [
                'supplier_tax_id' => '30500949461',
                'type' => SupplierVoucherType::DebitNote,
                'point_of_sale' => '0003',
                'number' => '00000987',
                'issue_date' => today()->subDays(3)->toDateString(),
                'due_date' => null,
                'net_amount' => '5000.00',
                'other_taxes_amount' => '0.00',
                'notes' => 'Nota de débito pendiente de imputar.',
            ],
        ];

        foreach ($vouchers as $voucherData) {
            $supplier = Supplier::query()->where('tax_id', $voucherData['supplier_tax_id'])->firstOrFail();

            $alreadyExists = SupplierVoucher::query()
                ->whereBelongsTo($supplier)
                ->where('type', $voucherData['type'])
                ->where('letter', SupplierVoucherLetter::A)
                ->where('point_of_sale', $voucherData['point_of_sale'])
                ->where('number', $voucherData['number'])
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $createSupplierVoucher->handle([
                'supplier_id' => $supplier->id,
                'type' => $voucherData['type']->value,
                'letter' => SupplierVoucherLetter::A->value,
                'point_of_sale' => $voucherData['point_of_sale'],
                'number' => $voucherData['number'],
                'issue_date' => $voucherData['issue_date'],
                'due_date' => $voucherData['due_date'],
                'net_amount' => $voucherData['net_amount'],
                'other_taxes_amount' => $voucherData['other_taxes_amount'],
                'notes' => $voucherData['notes'],
            ]);
        }
    }
}
