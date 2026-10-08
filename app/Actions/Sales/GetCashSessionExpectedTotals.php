<?php

namespace App\Actions\Sales;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\PaymentMethodKind;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;

/**
 * @phpstan-type ExpectedTotal array{
 *     payment_method: PaymentMethod,
 *     opening_amount: numeric-string,
 *     sales_amount: numeric-string,
 *     income_amount: numeric-string,
 *     expense_amount: numeric-string,
 *     expected_amount: numeric-string
 * }
 */
class GetCashSessionExpectedTotals
{
    use ConvertsMoneyToCents;

    /**
     * What each payment method should hold at the end of a session, from its movements (HU-060).
     *
     * One row per payment method with movements in the session. All cash movements are one
     * drawer, so they collapse into a single cash row, which is always present (even at $0)
     * because the closing always counts the bills. The expected amount is never typed: it is
     * the signed sum of the movements, opening + sales + incomes − expenses.
     *
     * @return list<ExpectedTotal>
     */
    public function handle(CashSession $cashSession): array
    {
        $movements = $cashSession->movements()
            ->with('paymentMethod')
            ->orderBy('id')
            ->get();

        /** @var array<int, array{payment_method: PaymentMethod, cents: array<string, int>}> $rows */
        $rows = [];
        $cashRowKey = null;

        foreach ($movements as $movement) {
            /** @var CashMovement $movement */
            $paymentMethod = $movement->paymentMethod;
            $key = $paymentMethod->id;

            if ($paymentMethod->kind === PaymentMethodKind::Cash) {
                $cashRowKey ??= $key;
                $key = $cashRowKey;
            }

            $rows[$key] ??= ['payment_method' => $paymentMethod, 'cents' => $this->emptyCents()];
            $rows[$key]['cents'][$movement->type->value] += $this->moneyToCents($movement->amount);
        }

        if ($cashRowKey === null) {
            $cashPaymentMethod = PaymentMethod::query()->cash()->first();

            if ($cashPaymentMethod !== null) {
                $rows = [$cashPaymentMethod->id => ['payment_method' => $cashPaymentMethod, 'cents' => $this->emptyCents()]] + $rows;
            }
        }

        uasort($rows, fn (array $a, array $b): int => [$this->kindOrder($a['payment_method']->kind), $a['payment_method']->name]
            <=> [$this->kindOrder($b['payment_method']->kind), $b['payment_method']->name]);

        return array_values(array_map(fn (array $row): array => $this->toTotal($row['payment_method'], $row['cents']), $rows));
    }

    /**
     * The expected cash in the drawer: the cash row of handle().
     *
     * @return numeric-string
     */
    public function expectedCash(CashSession $cashSession): string
    {
        foreach ($this->handle($cashSession) as $total) {
            if ($total['payment_method']->kind === PaymentMethodKind::Cash) {
                return $total['expected_amount'];
            }
        }

        return '0.00';
    }

    /**
     * @param  array<string, int>  $cents
     * @return ExpectedTotal
     */
    private function toTotal(PaymentMethod $paymentMethod, array $cents): array
    {
        $expectedCents = 0;

        foreach (CashMovementType::cases() as $type) {
            $expectedCents += $type->sign() * $cents[$type->value];
        }

        return [
            'payment_method' => $paymentMethod,
            'opening_amount' => $this->centsToMoney($cents[CashMovementType::Opening->value]),
            'sales_amount' => $this->centsToMoney($cents[CashMovementType::Sale->value]),
            'income_amount' => $this->centsToMoney($cents[CashMovementType::Income->value]),
            'expense_amount' => $this->centsToMoney($cents[CashMovementType::Expense->value]),
            'expected_amount' => $this->centsToMoney($expectedCents),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCents(): array
    {
        return array_fill_keys(array_map(fn (CashMovementType $type): string => $type->value, CashMovementType::cases()), 0);
    }

    /**
     * Cash first, then the other kinds in the enum's order, as the closing screen lists them.
     */
    private function kindOrder(PaymentMethodKind $kind): int
    {
        return (int) array_search($kind, PaymentMethodKind::cases(), true);
    }
}
