<?php

namespace App\Actions\Sales;

use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashDenomination;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\CashSessionStatus;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashSession
{
    /**
     * Open a cashier's session at a point of sale with the opening bill count (HU-057).
     *
     * The opening amount is the sum of denomination × quantity, never typed. It is recorded as the
     * first cash movement (`apertura`, in cash) unless it is zero: a zero opening is allowed, but a
     * movement must be greater than zero, and a missing movement adds nothing to the expected cash.
     *
     * Every denomination gets a count row, zeros included, so the closing (HU-060) compares
     * against a full count.
     *
     * @param  array{point_of_sale_id: int|string, counts: array<int|string, int|string>}  $data
     */
    public function handle(array $data, ?int $userId = null): CashSession
    {
        $userId ??= (int) auth()->id();

        $cashPaymentMethod = PaymentMethod::query()->cash()->first();

        if ($cashPaymentMethod === null) {
            throw ValidationException::withMessages([
                'point_of_sale_id' => 'No hay un medio de pago de efectivo activo. Dalo de alta o reactivalo para poder abrir la caja.',
            ]);
        }

        $counts = $this->normalizeCounts($data['counts']);
        $openingTotal = array_sum(array_map(
            fn (int $quantity, int $denomination): int => $quantity * $denomination,
            $counts,
            array_keys($counts),
        ));
        $openingAmount = number_format($openingTotal, 2, '.', '');

        try {
            return DB::transaction(function () use ($data, $userId, $counts, $openingTotal, $openingAmount, $cashPaymentMethod): CashSession {
                $pointOfSale = PointOfSale::query()->lockForUpdate()->findOrFail((int) $data['point_of_sale_id']);

                $this->ensureCanOpen($pointOfSale, $userId);

                $cashSession = CashSession::create([
                    'point_of_sale_id' => $pointOfSale->id,
                    'user_id' => $userId,
                    'status' => CashSessionStatus::Open,
                    'opened_at' => now(),
                    'opening_amount' => $openingAmount,
                ]);

                $cashSession->counts()->createMany(array_map(
                    fn (int $quantity, int $denomination): array => [
                        'moment' => CashCountMoment::Opening,
                        'denomination' => $denomination,
                        'quantity' => $quantity,
                    ],
                    $counts,
                    array_keys($counts),
                ));

                if ($openingTotal > 0) {
                    $cashSession->movements()->create([
                        'type' => CashMovementType::Opening,
                        'payment_method_id' => $cashPaymentMethod->id,
                        'amount' => $openingAmount,
                        'user_id' => $userId,
                    ]);
                }

                return $cashSession;
            });
        } catch (UniqueConstraintViolationException) {
            // Another opening won the race after the checks above: the partial unique indexes hold the rule.
            throw ValidationException::withMessages([
                'point_of_sale_id' => 'La caja o el cajero ya tienen un turno abierto.',
            ]);
        }
    }

    private function ensureCanOpen(PointOfSale $pointOfSale, int $userId): void
    {
        if (! $pointOfSale->is_active) {
            throw ValidationException::withMessages([
                'point_of_sale_id' => 'El punto de venta seleccionado no está activo.',
            ]);
        }

        $userSession = CashSession::query()->openForUser($userId)->with('pointOfSale')->first();

        if ($userSession !== null) {
            throw ValidationException::withMessages([
                'point_of_sale_id' => "Ya tenés un turno abierto en la caja {$userSession->pointOfSale->number}.",
            ]);
        }

        $pointOfSaleSession = CashSession::query()
            ->open()
            ->where('point_of_sale_id', $pointOfSale->id)
            ->with('user')
            ->first();

        if ($pointOfSaleSession !== null) {
            throw ValidationException::withMessages([
                'point_of_sale_id' => "La caja {$pointOfSale->number} ya tiene un turno abierto por {$pointOfSaleSession->user->name} desde las {$pointOfSaleSession->opened_at->format('H:i')}.",
            ]);
        }
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
