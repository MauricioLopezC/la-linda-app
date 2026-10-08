<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\CreateStockMovementFromSale;
use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleStatus;
use App\Events\Sales\SaleConfirmed;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSalePayment
{
    use ConvertsMoneyToCents;

    /**
     * Confirm a sale payment with one or multiple payment methods (EPIC-04).
     *
     * In cash, the movement records the amount that stays in the register (the sale amount),
     * and tendered_amount saves what was handed over for change calculation. Confirmation,
     * cash movements, stock deduction (EPIC-06) and invoicing (HU-042) occur atomically.
     *
     * @param  array<int, array{payment_method_id: int, amount: float|int|string, tendered_amount?: float|int|string|null}>  $payments
     *
     * @throws ValidationException
     */
    public function handle(Sale $sale, array $payments, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $payments, $user): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->status === SaleStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'sale' => 'La venta ya ha sido confirmada.',
                ]);
            }

            if ($lockedSale->status === SaleStatus::Discarded) {
                throw ValidationException::withMessages([
                    'sale' => 'No se puede cobrar una venta descartada.',
                ]);
            }

            if (! $lockedSale->items()->exists()) {
                throw ValidationException::withMessages([
                    'sale' => 'No se puede cobrar una venta sin artículos.',
                ]);
            }

            /*
             * Locking the session serializes the payment with the closing (HU-060): the closing
             * either sees this sale still open and rejects, or sees it confirmed with its movements.
             */
            $cashSession = $lockedSale->cash_session_id === null
                ? null
                : CashSession::query()->lockForUpdate()->find($lockedSale->cash_session_id);

            if ($cashSession?->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'sale' => 'El turno de caja de la venta no se encuentra abierto.',
                ]);
            }

            if (empty($payments)) {
                throw ValidationException::withMessages([
                    'payments' => 'Debe registrar al menos un medio de pago.',
                ]);
            }

            $methodIds = collect($payments)->pluck('payment_method_id')->unique()->all();
            $methods = PaymentMethod::query()->whereIn('id', $methodIds)->get()->keyBy('id');

            foreach ($payments as $index => $payment) {
                $method = $methods->get((int) $payment['payment_method_id']);

                if ($method === null || ! $method->is_active) {
                    $name = $method !== null ? $method->name : 'seleccionado';
                    throw ValidationException::withMessages([
                        "payments.{$index}.payment_method_id" => "El medio de pago '{$name}' no está activo o no existe.",
                    ]);
                }

                $amountCents = $this->moneyToCents((string) $payment['amount']);
                if ($amountCents <= 0) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.amount" => 'El importe de cada medio de pago debe ser mayor a cero.',
                    ]);
                }

                $tenderedRaw = $payment['tendered_amount'] ?? null;
                $hasTendered = $tenderedRaw !== null && $tenderedRaw !== '';

                if ($method->kind !== PaymentMethodKind::Cash && $hasTendered) {
                    $tenderedCents = $this->moneyToCents((string) $tenderedRaw);
                    if ($tenderedCents > $amountCents) {
                        throw ValidationException::withMessages([
                            "payments.{$index}.tendered_amount" => "Solo el efectivo puede recibir un importe mayor al cobrado (medio: {$method->name}).",
                        ]);
                    }
                }

                if ($method->kind === PaymentMethodKind::Cash && $hasTendered) {
                    $tenderedCents = $this->moneyToCents((string) $tenderedRaw);
                    if ($tenderedCents < $amountCents) {
                        throw ValidationException::withMessages([
                            "payments.{$index}.tendered_amount" => 'El importe entregado en efectivo no puede ser menor al importe a cobrar.',
                        ]);
                    }
                }
            }

            $totalPaymentsCents = collect($payments)->sum(fn (array $p): int => $this->moneyToCents((string) $p['amount']));
            $totalSaleCents = $this->moneyToCents($lockedSale->total_amount);

            if ($totalPaymentsCents !== $totalSaleCents) {
                throw ValidationException::withMessages([
                    'payments' => 'La suma de los importes coincide con el total de la venta; solo el efectivo puede recibir de más, y la diferencia es el vuelto.',
                ]);
            }

            foreach ($payments as $payment) {
                /** @var PaymentMethod $method */
                $method = $methods->get((int) $payment['payment_method_id']);
                $amount = $this->centsToMoney($this->moneyToCents((string) $payment['amount']));

                $tenderedAmount = null;
                $tenderedRaw = $payment['tendered_amount'] ?? null;
                if ($method->kind === PaymentMethodKind::Cash && $tenderedRaw !== null && $tenderedRaw !== '') {
                    $tenderedAmount = $this->centsToMoney($this->moneyToCents((string) $tenderedRaw));
                }

                CashMovement::create([
                    'cash_session_id' => $lockedSale->cash_session_id,
                    'type' => CashMovementType::Sale,
                    'payment_method_id' => $method->id,
                    'amount' => $amount,
                    'sale_id' => $lockedSale->id,
                    'tendered_amount' => $tenderedAmount,
                    'reason' => null,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);
            }

            $lockedSale->update([
                'status' => SaleStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            // Hooks for downstream sprint stories (EPIC-06 and HU-042)
            if (class_exists(CreateStockMovementFromSale::class)) {
                app(CreateStockMovementFromSale::class)->handle($lockedSale, $user->id);
            }

            if (class_exists(IssueInvoice::class)) {
                app(IssueInvoice::class)->handle($lockedSale, $user);
            }

            event(new SaleConfirmed($lockedSale, $user));

            return $lockedSale->fresh();
        });
    }
}
