<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\ReverseStockMovementForVoucher;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AnnulSupplierVoucher
{
    public function __construct(
        private EvaluatePurchaseOrderFulfillment $evaluateFulfillment,
        private UpdateLastPurchaseCost $updateLastPurchaseCost,
        private ReverseStockMovementForVoucher $reverseStockMovement,
    ) {}

    public function handle(SupplierVoucher $supplierVoucher, string $reason, ?int $userId = null): SupplierVoucher
    {
        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo de anulación es obligatorio.']);
        }

        $actualUserId = (int) ($userId ?? auth()->id());

        return DB::transaction(function () use ($supplierVoucher, $trimmedReason, $actualUserId): SupplierVoucher {
            $voucher = SupplierVoucher::query()->lockForUpdate()->findOrFail($supplierVoucher->id);

            if ($voucher->status === SupplierVoucherStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'El comprobante ya se encuentra anulado.']);
            }

            if (! $voucher->canBeAnnulled()) {
                throw ValidationException::withMessages([
                    'status' => 'El comprobante tiene aplicaciones u órdenes de pago vigentes que deben revertirse antes de anularlo.',
                ]);
            }

            $this->reverseStockMovement->handle($voucher, $actualUserId, $trimmedReason);

            $voucher->update([
                'status' => SupplierVoucherStatus::Cancelled,
                'annulled_at' => now(),
                'annulled_by' => $actualUserId,
                'annulment_reason' => $trimmedReason,
            ]);

            $affectedOrders = PurchaseOrder::query()
                ->whereHas('items.imputations', function ($query) use ($voucher) {
                    $query->whereIn('supplier_voucher_item_id', $voucher->items()->select('id'));
                })
                ->pluck('id')
                ->all();

            $this->evaluateFulfillment->handleMany($affectedOrders);
            $this->updateLastPurchaseCost->recalculateForAnnulledVoucher($voucher);

            Log::info('Supplier voucher annulled', [
                'supplier_voucher_id' => $voucher->id,
                'reason' => $voucher->annulment_reason,
                'user_id' => $voucher->annulled_by,
            ]);

            return $voucher;
        });
    }
}
