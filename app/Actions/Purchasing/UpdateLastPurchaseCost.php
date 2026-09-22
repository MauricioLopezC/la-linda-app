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
        $articleIds = $items->pluck('article_id')->unique()->sort()->values();
        $associations = ArticleSupplier::query()
            ->where('supplier_id', $voucher->supplier_id)
            ->whereIn('article_id', $articleIds)
            ->orderBy('article_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('article_id');

        foreach ($items as $item) {
            $association = $associations->get($item->article_id);

            if ($association === null) {
                throw ValidationException::withMessages([
                    'items.'.($item->position - 1).'.article_id' => 'Primero asociá el artículo con este proveedor e indicá su código de proveedor.',
                ]);
            }
        }

        $items->keyBy('article_id')->each(function (SupplierVoucherItem $item) use ($associations): void {
            $associations->get($item->article_id)?->update(['last_cost' => $item->unit_price]);
        });
    }

    public function recalculateForAnnulledVoucher(SupplierVoucher $voucher): void
    {
        if (! $voucher->type->isInvoice()) {
            return;
        }

        $articleIds = $voucher->items()
            ->whereNotNull('article_id')
            ->distinct()
            ->orderBy('article_id')
            ->pluck('article_id');
        $associations = ArticleSupplier::query()
            ->where('supplier_id', $voucher->supplier_id)
            ->whereIn('article_id', $articleIds)
            ->orderBy('article_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('article_id');
        $lastValidItems = SupplierVoucherItem::query()
            ->whereIn('article_id', $articleIds)
            ->whereHas('supplierVoucher', function ($query) use ($voucher) {
                $query->where('supplier_id', $voucher->supplier_id)
                    ->where('type', SupplierVoucherType::Invoice)
                    ->where('status', '!=', SupplierVoucherStatus::Cancelled);
            })
            ->orderByDesc('supplier_voucher_id')
            ->orderByDesc('position')
            ->groupLimit(1, 'article_id')
            ->get()
            ->keyBy('article_id');

        foreach ($articleIds as $articleId) {
            $association = $associations->get($articleId);

            if ($association === null) {
                continue;
            }

            $association->update(['last_cost' => $lastValidItems->get($articleId)?->unit_price]);
        }
    }
}
