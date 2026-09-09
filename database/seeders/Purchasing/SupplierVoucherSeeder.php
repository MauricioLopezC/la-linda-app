<?php

namespace Database\Seeders\Purchasing;

use App\Actions\Purchasing\CreateSupplierVoucher;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Seeder;
use RuntimeException;

class SupplierVoucherSeeder extends Seeder
{
    public function run(CreateSupplierVoucher $createSupplierVoucher): void
    {
        $articles = Article::query()->active()->with('unitOfMeasure')->orderBy('id')->get();

        if ($articles->count() < 3) {
            throw new RuntimeException('Se requieren al menos tres artículos activos para simular comprobantes.');
        }

        $vouchers = [
            ['30500858628', SupplierVoucherType::Invoice, SupplierVoucherLetter::A, '0001', '00012001', -20, 10, '125000.00'],
            ['30502793175', SupplierVoucherType::Invoice, SupplierVoucherLetter::B, '0002', '00004567', -60, -30, '60500.00'],
            ['30500511849', SupplierVoucherType::CreditNote, SupplierVoucherLetter::C, '0003', '00000321', -5, null, '10000.00'],
            ['30500949461', SupplierVoucherType::DebitNote, SupplierVoucherLetter::M, '0004', '00000987', -3, 12, '5000.00'],
            ['20289456121', SupplierVoucherType::Invoice, SupplierVoucherLetter::A, '0005', '00007854', -12, 18, '98750.40'],
            ['30708945123', SupplierVoucherType::DebitNote, SupplierVoucherLetter::B, '0006', '00000146', -40, -10, '32150.75'],
        ];

        foreach ($vouchers as $voucherIndex => [$taxId, $type, $letter, $pointOfSale, $number, $issueOffset, $dueOffset, $total]) {
            $supplier = Supplier::query()->where('tax_id', $taxId)->firstOrFail();

            if (SupplierVoucher::query()
                ->whereBelongsTo($supplier)
                ->where('type', $type)
                ->where('letter', $letter)
                ->where('point_of_sale', $pointOfSale)
                ->where('number', $number)
                ->exists()) {
                continue;
            }

            $items = collect(range(0, 2))->map(function (int $itemIndex) use ($articles, $voucherIndex): array {
                $article = $articles->get((($voucherIndex * 3) + $itemIndex) % $articles->count());

                if ($article === null) {
                    throw new RuntimeException('No se pudo seleccionar un artículo para el comprobante simulado.');
                }

                $quantity = ($voucherIndex + 1) + (($itemIndex + 1) / 4);
                $unitPrice = 750 + ($voucherIndex * 125) + ($itemIndex * 80);

                return [
                    'article_id' => $article->id,
                    'description' => $article->description,
                    'quantity' => number_format($quantity, 2, '.', ''),
                    'unit_of_measure' => $article->unitOfMeasure->abbreviation ?? $article->unitOfMeasure->name,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'line_total' => number_format($quantity * $unitPrice, 2, '.', ''),
                ];
            })->all();

            $createSupplierVoucher->handle([
                'supplier_id' => $supplier->id,
                'type' => $type->value,
                'letter' => $letter->value,
                'point_of_sale' => $pointOfSale,
                'number' => $number,
                'issue_date' => today()->addDays($issueOffset)->toDateString(),
                'due_date' => $dueOffset === null ? null : today()->addDays($dueOffset)->toDateString(),
                'total_amount' => $total,
                'notes' => 'Comprobante simulado '.($voucherIndex + 1).' con tres artículos.',
                'items' => $items,
            ]);
        }
    }
}
