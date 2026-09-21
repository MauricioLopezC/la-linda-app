<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use Illuminate\Validation\ValidationException;

class UpdateLastPurchaseCost
{
    public function handle(SupplierVoucher $voucher): void
    {
        if (! $voucher->type->isInvoice()) {
            return;
        }

        $items = $voucher->items()->whereNotNull('article_id')->orderBy('position')->get();

        foreach ($items as $item) {
            $association = ArticleSupplier::query()
                ->where('article_id', $item->article_id)
                ->where('supplier_id', $voucher->supplier_id)
                ->lockForUpdate()
                ->first();

            if ($association === null) {
                throw ValidationException::withMessages([
                    'items.'.($item->position - 1).'.article_id' => 'Primero asociá el artículo con este proveedor e indicá su código de proveedor.',
                ]);
            }

            $association->update(['last_cost' => $item->unit_price]);
        }
    }

    public function recalculateForAnnulledVoucher(SupplierVoucher $voucher): void
    {
        if (! $voucher->type->isInvoice()) {
            return;
        }

        $articleIds = $voucher->items()->whereNotNull('article_id')->distinct()->pluck('article_id');

        foreach ($articleIds as $articleId) {
            $association = ArticleSupplier::query()
                ->where('article_id', $articleId)
                ->where('supplier_id', $voucher->supplier_id)
                ->lockForUpdate()
                ->first();

            if ($association === null) {
                continue;
            }

            $lastValidItem = SupplierVoucherItem::query()
                ->where('article_id', $articleId)
                ->whereHas('supplierVoucher', function ($query) use ($voucher) {
                    $query->where('supplier_id', $voucher->supplier_id)
                        ->where('type', SupplierVoucherType::Invoice)
                        ->where('status', '!=', SupplierVoucherStatus::Cancelled);
                })
                ->orderByDesc('supplier_voucher_id')
                ->orderByDesc('position')
                ->first();

            $association->update(['last_cost' => $lastValidItem?->unit_price]);
        }
    }
}
