<?php

namespace App\Actions\Sales;

use App\Enums\Sales\CashMovementType;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterCashMovement
{
    /**
     * Register a cash movement (income or expense) inside an open cash session (HU-058).
     *
     * Incomes and expenses are registered in cash and require a reason. An expense cannot
     * exceed the available cash in the drawer according to recorded session movements.
     * Cash movements are immutable; a mistake is corrected with the opposite movement.
     *
     * @param  array{type: CashMovementType|string, amount: float|int|string, reason: string}  $data
     */
    public function handle(CashSession $cashSession, array $data, ?int $userId = null): CashMovement
    {
        $userId ??= (int) auth()->id();

        return DB::transaction(function () use ($cashSession, $data, $userId): CashMovement {
            /** @var CashSession $session */
            $session = CashSession::query()
                ->lockForUpdate()
                ->findOrFail($cashSession->id);

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Solo se pueden registrar movimientos en un turno de caja abierto.',
                ]);
            }

            $type = is_string($data['type'])
                ? CashMovementType::tryFrom($data['type'])
                : $data['type'];

            if (! in_array($type, [CashMovementType::Income, CashMovementType::Expense], true)) {
                throw ValidationException::withMessages([
                    'type' => 'El tipo de movimiento manual debe ser ingreso o egreso.',
                ]);
            }

            $amount = number_format((float) $data['amount'], 2, '.', '');

            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El importe debe ser mayor a cero.',
                ]);
            }

            $reason = trim($data['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'El motivo es obligatorio.',
                ]);
            }

            $cashPaymentMethod = PaymentMethod::query()->cash()->first();

            if ($cashPaymentMethod === null) {
                throw ValidationException::withMessages([
                    'amount' => 'No hay un medio de pago de efectivo activo para registrar el movimiento.',
                ]);
            }

            if ($type === CashMovementType::Expense) {
                $availableCash = $session->expectedCash();

                /* The message leaves the available amount out: the closing count is blind (HU-060). */
                if (bccomp($amount, $availableCash, 2) === 1) {
                    throw ValidationException::withMessages([
                        'amount' => 'El importe del egreso supera el efectivo disponible en la caja según el sistema.',
                    ]);
                }
            }

            return $session->movements()->create([
                'type' => $type,
                'payment_method_id' => $cashPaymentMethod->id,
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => $userId,
            ]);
        });
    }
}
