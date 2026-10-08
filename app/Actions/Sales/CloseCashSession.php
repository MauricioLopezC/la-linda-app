<?php

namespace App\Actions\Sales;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashDenomination;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleStatus;
use App\Models\Sales\CashSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashSession
{
    use ConvertsMoneyToCents;

    public function __construct(private GetCashSessionExpectedTotals $expectedTotals) {}

    /**
     * Close a cash session with its count per payment method (HU-060).
     *
     * Cash is declared bill by bill: its declared amount is the sum of the closing count, never
     * typed. Every other payment method with movements in the session is declared as an amount,
     * and cards also carry the POSNET batch number. Each method gets a closure line with expected
     * (from the movements), declared and difference (declared − expected: positive is a surplus,
     * negative a shortage). Any difference makes the notes mandatory.
     *
     * The session must have no open sales. Once closed it is immutable: sales, payments and
     * movements all require an open session, and the point of sale is free for a new one.
     *
     * @param  array{
     *     counts: array<int|string, int|string>,
     *     declarations?: array<int|string, array{declared_amount?: float|int|string|null, batch_reference?: string|null}>,
     *     closing_notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function handle(CashSession $cashSession, array $data): CashSession
    {
        return DB::transaction(function () use ($cashSession, $data): CashSession {
            /** @var CashSession $session */
            $session = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'El turno de caja ya está cerrado.',
                ]);
            }

            $openSales = $session->sales()->where('status', SaleStatus::Open)->count();

            if ($openSales > 0) {
                throw ValidationException::withMessages([
                    'cash_session' => $openSales === 1
                        ? 'Hay 1 venta abierta en el turno. Cobrala o descartala antes de cerrar la caja.'
                        : "Hay {$openSales} ventas abiertas en el turno. Cobralas o descartalas antes de cerrar la caja.",
                ]);
            }

            $counts = $this->normalizeCounts($data['counts']);
            $countedCashCents = 100 * array_sum(array_map(
                fn (int $quantity, int $denomination): int => $quantity * $denomination,
                $counts,
                array_keys($counts),
            ));

            $declarations = $data['declarations'] ?? [];
            $lines = [];
            $hasDifference = false;

            foreach ($this->expectedTotals->handle($session) as $total) {
                $paymentMethod = $total['payment_method'];
                $declaration = $declarations[$paymentMethod->id] ?? [];

                if ($paymentMethod->kind === PaymentMethodKind::Cash) {
                    $declaredCents = $countedCashCents;
                } else {
                    $declaredCents = $this->declaredCents($paymentMethod->id, $paymentMethod->name, $declaration['declared_amount'] ?? null);
                }

                $batchReference = null;

                if ($paymentMethod->kind->requiresBatchReference()) {
                    $batchReference = trim((string) ($declaration['batch_reference'] ?? ''));

                    if ($batchReference === '') {
                        throw ValidationException::withMessages([
                            "declarations.{$paymentMethod->id}.batch_reference" => "Ingresá el número de lote del cierre del POSNET de {$paymentMethod->name}.",
                        ]);
                    }
                }

                $differenceCents = $declaredCents - $this->moneyToCents($total['expected_amount']);
                $hasDifference = $hasDifference || $differenceCents !== 0;

                $lines[] = [
                    'payment_method_id' => $paymentMethod->id,
                    'expected_amount' => $total['expected_amount'],
                    'declared_amount' => $this->centsToMoney($declaredCents),
                    'difference' => $this->centsToMoney($differenceCents),
                    'batch_reference' => $batchReference,
                ];
            }

            $closingNotes = trim((string) ($data['closing_notes'] ?? ''));

            if ($hasDifference && $closingNotes === '') {
                throw ValidationException::withMessages([
                    'closing_notes' => 'El arqueo tiene diferencias: explicá el motivo en la observación.',
                ]);
            }

            $session->counts()->createMany(array_map(
                fn (int $quantity, int $denomination): array => [
                    'moment' => CashCountMoment::Closing,
                    'denomination' => $denomination,
                    'quantity' => $quantity,
                ],
                $counts,
                array_keys($counts),
            ));

            $session->closureLines()->createMany($lines);

            $session->update([
                'status' => CashSessionStatus::Closed,
                'closed_at' => now(),
                'closing_notes' => $closingNotes === '' ? null : $closingNotes,
            ]);

            return $session;
        });
    }

    private function declaredCents(int $paymentMethodId, string $paymentMethodName, float|int|string|null $declaredAmount): int
    {
        $field = "declarations.{$paymentMethodId}.declared_amount";

        if ($declaredAmount === null || $declaredAmount === '') {
            throw ValidationException::withMessages([
                $field => "Ingresá el importe cobrado con {$paymentMethodName}.",
            ]);
        }

        $cents = $this->moneyToCents(is_float($declaredAmount) ? number_format($declaredAmount, 2, '.', '') : (string) $declaredAmount);

        if ($cents < 0) {
            throw ValidationException::withMessages([
                $field => 'El importe declarado no puede ser negativo.',
            ]);
        }

        return $cents;
    }

    /**
     * One entry per current denomination, highest first; a missing denomination counts as zero.
     *
     * @param  array<int|string, int|string>  $counts
     * @return array<int, int>
     */
    private function normalizeCounts(array $counts): array
    {
        $normalized = [];

        foreach (CashDenomination::cases() as $denomination) {
            $normalized[$denomination->value] = (int) ($counts[$denomination->value] ?? 0);
        }

        return $normalized;
    }
}
