<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateSupplierVoucher
{
    use ConvertsMoneyToCents;

    public function __construct(
        private ResolveSupplierVoucherStatus $resolveStatus,
        private AssociateCreditNoteToInvoice $associateCreditNote,
    ) {}

    /**
     * @param  array{
     *     supplier_id: int, type: string, letter: string, point_of_sale: string, number: string,
     *     issue_date: string, due_date: ?string, total_amount: string, notes: ?string,
     *     associated_invoice_id?: ?int, associated_amount?: ?string,
     *     items: array<int, array{article_id: ?int, description: string, quantity: string,
     *         unit_of_measure: string, unit_price: string, line_total: string}>
     * }  $data
     */
    public function handle(array $data): SupplierVoucher
    {
        return DB::transaction(function () use ($data): SupplierVoucher {
            $supplier = Supplier::query()->active()->lockForUpdate()->find($data['supplier_id']);

            if ($supplier === null) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'El proveedor seleccionado no existe o está inactivo.',
                ]);
            }

            $this->ensureArticlesRemainActive($data['items']);

            $type = SupplierVoucherType::from($data['type']);
            $totalAmount = $this->centsToMoney($this->moneyToCents($data['total_amount']));

            $voucher = SupplierVoucher::create([
                'supplier_id' => $supplier->id,
                'type' => $type,
                'letter' => SupplierVoucherLetter::from($data['letter']),
                'point_of_sale' => $data['point_of_sale'],
                'number' => $data['number'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'total_amount' => $totalAmount,
                'status' => $this->resolveStatus->handle($type, $totalAmount, $totalAmount),
                'notes' => $data['notes'],
            ]);

            foreach ($data['items'] as $index => $item) {
                $voucher->items()->create([...$item, 'position' => $index + 1]);
            }

            Log::info('Supplier voucher created', [
                'supplier_voucher_id' => $voucher->id,
                'supplier_id' => $supplier->id,
                'fiscal_number' => $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
                'items_count' => count($data['items']),
                'user_id' => auth()->id(),
            ]);

            $associatedInvoiceId = $data['associated_invoice_id'] ?? null;
            $associatedAmount = $data['associated_amount'] ?? null;

            if ($type->isCreditNote() && $associatedInvoiceId !== null && $associatedAmount !== null) {
                $this->associateCreditNote->handle($voucher, (int) $associatedInvoiceId, (string) $associatedAmount);
                $voucher->refresh();
            }

            return $voucher->load(['supplier', 'items.article']);
        });
    }

    /**
     * @param  array<int, array{
     *     article_id: ?int, description: string, quantity: string,
     *     unit_of_measure: string, unit_price: string, line_total: string
     * }>  $items
     */
    private function ensureArticlesRemainActive(array $items): void
    {
        $articleIds = collect($items)->pluck('article_id')->filter()->unique()->values();

        if ($articleIds->isEmpty()) {
            return;
        }

        $activeIds = Article::query()
            ->whereIn('id', $articleIds)
            ->where('status', ArticleStatus::Active)
            ->lockForUpdate()
            ->pluck('id');

        $invalidIds = $articleIds->diff($activeIds);

        if ($invalidIds->isEmpty()) {
            return;
        }

        $invalidIndex = collect($items)->search(
            fn (array $item): bool => $item['article_id'] !== null && $invalidIds->contains($item['article_id'])
        );

        throw ValidationException::withMessages([
            'items.'.($invalidIndex === false ? 0 : $invalidIndex).'.article_id' => 'El artículo seleccionado no existe o está inactivo.',
        ]);
    }
}
