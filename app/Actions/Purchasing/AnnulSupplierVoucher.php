<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AnnulSupplierVoucher
{
    public function handle(SupplierVoucher $supplierVoucher, string $reason, ?int $userId = null): SupplierVoucher
    {
        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo de anulación es obligatorio.']);
        }

        return DB::transaction(function () use ($supplierVoucher, $trimmedReason, $userId): SupplierVoucher {
            $voucher = SupplierVoucher::query()->lockForUpdate()->findOrFail($supplierVoucher->id);

            if ($voucher->status === SupplierVoucherStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'El comprobante ya se encuentra anulado.']);
            }

            if (! $voucher->canBeAnnulled()) {
                throw ValidationException::withMessages([
                    'status' => 'El comprobante tiene aplicaciones u órdenes de pago vigentes que deben revertirse antes de anularlo.',
                ]);
            }

            $voucher->update([
                'status' => SupplierVoucherStatus::Cancelled,
                'annulled_at' => now(),
                'annulled_by' => $userId ?? auth()->id(),
                'annulment_reason' => $trimmedReason,
            ]);

            Log::info('Supplier voucher annulled', [
                'supplier_voucher_id' => $voucher->id,
                'reason' => $voucher->annulment_reason,
                'user_id' => $voucher->annulled_by,
            ]);

            return $voucher;
        });
    }
}
