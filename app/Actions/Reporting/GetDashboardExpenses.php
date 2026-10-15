<?php

namespace App\Actions\Reporting;

use App\Concerns\ConvertsMoneyToCents;
use App\Data\Reporting\DashboardBreakdownItemData;
use App\Data\Reporting\DashboardExpensesData;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Enums\Sales\CashMovementType;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\CashMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GetDashboardExpenses
{
    use ConvertsMoneyToCents;

    /**
     * Purchases are global; only operational cash expenses can be attributed to a branch.
     *
     * @param  array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}  $filters
     */
    public function handle(array $filters): DashboardExpensesData
    {
        $accruedCents = 0;
        $vouchers = SupplierVoucher::query()
            ->select(['id', 'type', 'total_amount'])
            ->whereIn('type', [SupplierVoucherType::Invoice, SupplierVoucherType::DebitNote, SupplierVoucherType::CreditNote])
            ->where('status', '!=', SupplierVoucherStatus::Cancelled)
            ->whereDate('issue_date', '>=', $filters['date_from'])
            ->whereDate('issue_date', '<=', $filters['date_to']);

        foreach ($vouchers->lazyById(500) as $voucher) {
            $amountCents = $this->moneyToCents($voucher->total_amount);
            $accruedCents += $voucher->type === SupplierVoucherType::CreditNote ? -$amountCents : $amountCents;
        }

        $paidCents = 0;
        /** @var array<int, array{label: string, cents: int, count: int}> $byMethod */
        $byMethod = [];
        $orders = PaymentOrder::query()
            ->select(['id', 'total_amount'])
            ->where('status', PaymentOrderStatus::Issued)
            ->whereDate('date', '>=', $filters['date_from'])
            ->whereDate('date', '<=', $filters['date_to'])
            ->with(['paymentMethods:id,payment_order_id,payment_method_id,amount', 'paymentMethods.paymentMethod:id,name']);

        foreach ($orders->lazyById(500) as $order) {
            $paidCents += $this->moneyToCents($order->total_amount);
            $countedMethods = [];
            foreach ($order->paymentMethods as $method) {
                $methodId = $method->payment_method_id;
                $byMethod[$methodId] ??= ['label' => $method->paymentMethod->name, 'cents' => 0, 'count' => 0];
                $byMethod[$methodId]['cents'] += $this->moneyToCents($method->amount);
                if (! isset($countedMethods[$methodId])) {
                    $byMethod[$methodId]['count']++;
                    $countedMethods[$methodId] = true;
                }
            }
        }

        $cashCents = 0;
        $movements = CashMovement::query()
            ->select(['id', 'amount'])
            ->where('type', CashMovementType::Expense)
            ->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay())
            ->where('created_at', '<', Carbon::parse($filters['date_to'])->startOfDay()->addDay())
            ->when($filters['branch_id'] !== null, fn (Builder $query): Builder => $query
                ->whereHas('cashSession.pointOfSale.warehouse', fn (Builder $warehouse): Builder => $warehouse
                    ->where('branch_id', $filters['branch_id'])));

        foreach ($movements->lazyById(500) as $movement) {
            $cashCents += $this->moneyToCents($movement->amount);
        }

        ksort($byMethod);
        $breakdown = [];
        foreach ($byMethod as $id => $method) {
            $breakdown[] = new DashboardBreakdownItemData(
                key: (string) $id,
                label: $method['label'],
                amount: $this->centsToMoney($method['cents']),
                count: $method['count'],
            );
        }

        return new DashboardExpensesData(
            accrued_total: $this->centsToMoney($accruedCents),
            paid_to_suppliers_total: $this->centsToMoney($paidCents),
            cash_expenses_total: $this->centsToMoney($cashCents),
            disbursed_total: $this->centsToMoney($paidCents + $cashCents),
            by_payment_method: $breakdown,
        );
    }
}
