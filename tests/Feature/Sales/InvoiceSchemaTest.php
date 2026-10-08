<?php

use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Sales\InvoiceType;
use App\Models\Inventory\StockMovement;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the point of sale snapshot migration backfills existing invoices and preserves their constraints', function () {
    $invoice = Invoice::factory()->create();
    $originalNumber = $invoice->pointOfSale->number;
    $migration = require database_path('migrations/2026_10_06_154922_add_point_of_sale_number_to_invoices_table.php');

    $migration->down();
    expect(Schema::hasColumn('invoices', 'point_of_sale_number'))->toBeFalse();

    $migration->up();
    $invoice->pointOfSale->update(['number' => $originalNumber + 1]);
    $invoice->refresh();

    expect($invoice->point_of_sale_number)->toBe($originalNumber)
        ->and($invoice->formattedNumber())->toBe(sprintf('%04d-%08d', $originalNumber, $invoice->number))
        ->and(inSavepoint(fn () => DB::table('invoices')->where('id', $invoice->id)->update(['type' => 'C'])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => DB::table('invoices')->where('id', $invoice->id)->update(['total_amount' => '1200.00'])))
        ->toThrow(QueryException::class);
});

test('invoice numbers are unique per point of sale and type', function () {
    $first = Invoice::factory()->create();

    $sameCounter = Sale::factory()->confirmed()->create(['point_of_sale_id' => $first->point_of_sale_id]);

    expect(inSavepoint(fn () => Invoice::factory()->create([
        'sale_id' => $sameCounter->id,
        'number' => $first->number,
    ])))->toThrow(QueryException::class);

    $typeA = Invoice::factory()->typeA()->create([
        'sale_id' => $sameCounter->id,
        'number' => $first->number,
    ]);

    expect($typeA->type)->toBe(InvoiceType::A)
        ->and($typeA->pointOfSale->invoices()->count())->toBe(2);
});

test('a sale has at most one invoice', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->sale->invoice->id)->toBe($invoice->id)
        ->and(inSavepoint(fn () => Invoice::factory()->create(['sale_id' => $invoice->sale_id])))
        ->toThrow(QueryException::class);
});

test('an invoice total is its net plus its VAT', function () {
    expect(inSavepoint(fn () => Invoice::factory()->create([
        'net_amount' => '1000.00',
        'vat_amount' => '210.00',
        'total_amount' => '1200.00',
    ])))->toThrow(QueryException::class);
});

test('an invoice line total is its net plus its VAT', function () {
    $item = InvoiceItem::factory()->create();

    expect($item->invoice->items()->sole()->line_total)->toBe('1210.00')
        ->and(inSavepoint(fn () => InvoiceItem::factory()->create([
            'net_amount' => '1000.00',
            'vat_amount' => '200.00',
            'line_total' => '1210.00',
        ])))->toThrow(QueryException::class);
});

test('La Linda never issues a C invoice', function () {
    $invoice = Invoice::factory()->create();

    expect(inSavepoint(fn () => DB::table('invoices')->where('id', $invoice->id)->update(['type' => 'C'])))
        ->toThrow(QueryException::class);
});

test('the invoice type follows the customer tax condition', function (CustomerTaxCondition $taxCondition, InvoiceType $expected) {
    expect(InvoiceType::forTaxCondition($taxCondition))->toBe($expected);
})->with([
    'responsable inscripto' => [CustomerTaxCondition::ResponsibleInscripto, InvoiceType::A],
    'monotributo' => [CustomerTaxCondition::Monotributo, InvoiceType::B],
    'consumidor final' => [CustomerTaxCondition::ConsumidorFinal, InvoiceType::B],
    'exento' => [CustomerTaxCondition::Exento, InvoiceType::B],
]);

test('a sale originates at most one stock movement', function () {
    $sale = Sale::factory()->confirmed()->create();
    $movement = StockMovement::factory()->create(['sale_id' => $sale->id]);

    expect($sale->stockMovement->id)->toBe($movement->id)
        ->and($movement->sale->id)->toBe($sale->id)
        ->and(inSavepoint(fn () => StockMovement::factory()->create(['sale_id' => $sale->id])))
        ->toThrow(QueryException::class);
});
