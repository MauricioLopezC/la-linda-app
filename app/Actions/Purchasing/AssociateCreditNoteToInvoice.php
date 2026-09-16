<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Impute a credit note onto an invoice of the same supplier while the note is being registered
 * (HU-054, "vinculación en la carga").
 *
 * This is the only write path for `voucher_applications`: there is no standalone re-imputation
 * screen. A free credit note, or the part of one left unapplied here, is compensated later inside
 * a payment order (HU-027), never through this action.
 *
 * The imputation is immutable: there is no update or delete route, so a mistake is undone by
 * annulling the credit note and loading a new one, not by editing the row.
 */
class AssociateCreditNoteToInvoice
{
    use ConvertsMoneyToCents;

    public function __construct(private RecalculateVoucherBalanceStatus $recalculateStatus) {}

    /**
     * @param  string  $amount  Positive money string; must fit within both the invoice's pending
     *                          balance and the credit note's still-unapplied amount.
     */
    public function handle(
        SupplierVoucher $creditNote,
        int $invoiceId,
        string $amount,
        ?int $userId = null,
    ): VoucherApplication {
        $responsibleUserId = $userId ?? auth()->id();

        if ($responsibleUserId === null) {
            throw new LogicException('La asociación de una nota de crédito requiere un usuario responsable.');
        }

        return DB::transaction(function () use ($creditNote, $invoiceId, $amount, $responsibleUserId): VoucherApplication {
            $note = SupplierVoucher::query()->lockForUpdate()->findOrFail($creditNote->id);

            if ($note->type !== SupplierVoucherType::CreditNote) {
                throw ValidationException::withMessages([
                    'associated_invoice_id' => 'Solo una nota de crédito puede asociarse a una factura.',
                ]);
            }

            if ($note->status === SupplierVoucherStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'associated_invoice_id' => 'La nota de crédito está anulada.',
                ]);
            }

            $invoice = SupplierVoucher::query()
                ->where('supplier_id', $note->supplier_id)
                ->where('type', SupplierVoucherType::Invoice)
                ->lockForUpdate()
                ->find($invoiceId);

            if ($invoice === null) {
                throw ValidationException::withMessages([
                    'associated_invoice_id' => 'La factura seleccionada no existe o no pertenece al proveedor de la nota.',
                ]);
            }

            if ($invoice->status === SupplierVoucherStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'associated_invoice_id' => 'La factura seleccionada está anulada.',
                ]);
            }

            $amountCents = $this->moneyToCents($amount);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'associated_amount' => 'El importe aplicado debe ser mayor a cero.',
                ]);
            }

            if ($amountCents > $this->moneyToCents($invoice->pendingBalance())) {
                throw ValidationException::withMessages([
                    'associated_amount' => 'El importe aplicado no puede superar el saldo pendiente de la factura.',
                ]);
            }

            if ($amountCents > $this->moneyToCents($note->unappliedAmount())) {
                throw ValidationException::withMessages([
                    'associated_amount' => 'El importe aplicado no puede superar el importe disponible de la nota de crédito.',
                ]);
            }

            $application = VoucherApplication::create([
                'source_voucher_id' => $note->id,
                'target_voucher_id' => $invoice->id,
                'amount' => $this->centsToMoney($amountCents),
                'user_id' => $responsibleUserId,
            ]);

            $this->recalculateStatus->handle($note);
            $this->recalculateStatus->handle($invoice);

            Log::info('Credit note imputed to invoice', [
                'voucher_application_id' => $application->id,
                'source_voucher_id' => $note->id,
                'target_voucher_id' => $invoice->id,
                'amount' => $application->amount,
                'user_id' => $application->user_id,
            ]);

            return $application;
        });
    }
}
