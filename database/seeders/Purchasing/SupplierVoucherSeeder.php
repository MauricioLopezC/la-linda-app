<?php

namespace Database\Seeders\Purchasing;

use App\Actions\Purchasing\AssociateCreditNoteToInvoice;
use App\Actions\Purchasing\CreateSupplierVoucher;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use RuntimeException;

class SupplierVoucherSeeder extends Seeder
{
    public function run(
        CreateSupplierVoucher $createSupplierVoucher,
        AssociateCreditNoteToInvoice $associateCreditNoteToInvoice,
    ): void {
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

        $this->seedCreditNoteAssociatedToInvoice(
            $createSupplierVoucher,
            $associateCreditNoteToInvoice,
            $articles,
        );
    }

    /**
     * Demo of HU-054 "vinculación en la carga": a credit note that immediately lowers the balance
     * of the first invoice of its supplier. The remaining free credit note (C 0003-00000321,
     * seeded above) covers the compensation-in-a-payment-order path.
     *
     * @param  Collection<int, Article>  $articles
     */
    private function seedCreditNoteAssociatedToInvoice(
        CreateSupplierVoucher $createSupplierVoucher,
        AssociateCreditNoteToInvoice $associateCreditNoteToInvoice,
        Collection $articles,
    ): void {
        if ($articles->count() < 3) {
            return;
        }

        $supplier = Supplier::query()->where('tax_id', '30500858628')->firstOrFail();

        $invoice = SupplierVoucher::query()
            ->whereBelongsTo($supplier)
            ->where('type', SupplierVoucherType::Invoice)
            ->orderBy('id')
            ->first();

        if ($invoice === null) {
            return;
        }

        $alreadySeeded = SupplierVoucher::query()
            ->whereBelongsTo($supplier)
            ->where('type', SupplierVoucherType::CreditNote)
            ->where('letter', SupplierVoucherLetter::A->value)
            ->where('point_of_sale', '0001')
            ->where('number', '00000045')
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        $items = $articles->take(3)->values()->map(fn (Article $article): array => [
            'article_id' => $article->id,
            'description' => $article->description,
            'quantity' => '1.00',
            'unit_of_measure' => $article->unitOfMeasure->abbreviation ?? $article->unitOfMeasure->name,
            'unit_price' => '5000.00',
            'line_total' => '5000.00',
        ])->all();

        $creditNote = $createSupplierVoucher->handle([
            'supplier_id' => $supplier->id,
            'type' => SupplierVoucherType::CreditNote->value,
            'letter' => SupplierVoucherLetter::A->value,
            'point_of_sale' => '0001',
            'number' => '00000045',
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => null,
            'total_amount' => '15000.00',
            'notes' => 'Nota de crédito asociada a la primera factura del proveedor.',
            'items' => $items,
        ]);

        $userId = User::query()->min('id');

        if ($userId === null) {
            // No users to attribute the imputation to (e.g. a bare production seed); leave the
            // credit note free instead of failing the seeder.
            return;
        }

        $associateCreditNoteToInvoice->handle($creditNote, $invoice->id, '15000.00', (int) $userId);
    }
}
