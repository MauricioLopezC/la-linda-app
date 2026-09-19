<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Data\Purchasing\SupplierAccountStatementItemData;
use App\Data\Purchasing\SupplierAccountStatementTotalsData;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetSupplierAccountStatement
{
    use ConvertsMoneyToCents;

    /**
     * Build the account statement for one supplier: header totals derived as
     * received − balance = paid, plus the detail of vouchers still pending.
     *
     * The balance is never a stored column — it comes from SupplierVoucher::outstandingAmount(),
     * the same derivation HU-027/HU-036/HU-054 already rely on, so this statement can never drift
     * from the balance shown anywhere else in the module.
     *
     * @param  array{date_from?: ?string, date_to?: ?string}  $filters
     * @return array{totals: SupplierAccountStatementTotalsData, items: Collection<int, SupplierAccountStatementItemData>}
     */
    public function execute(Supplier $supplier, array $filters = []): array
    {
        $vouchers = $this->payableVouchersQuery($supplier, $filters)->get();

        $receivedCents = 0;
        $balanceCents = 0;
        $items = [];

        foreach ($vouchers as $voucher) {
            $totalCents = $this->moneyToCents((string) $voucher->total_amount);
            $outstanding = $voucher->outstandingAmount();
            $outstandingCents = $this->moneyToCents($outstanding);

            $receivedCents += $totalCents;
            $balanceCents += $outstandingCents;

            if ($outstandingCents > 0) {
                $items[] = new SupplierAccountStatementItemData(
                    id: $voucher->id,
                    type: $voucher->type->value,
                    type_label: $voucher->type->label(),
                    formatted_number: $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
                    issue_date: $voucher->issue_date->toDateString(),
                    issue_date_formatted: $voucher->issue_date->format('d/m/Y'),
                    due_date: $voucher->due_date?->toDateString(),
                    due_date_formatted: $voucher->due_date?->format('d/m/Y'),
                    total_amount: $voucher->total_amount,
                    paid_amount: $this->centsToMoney($totalCents - $outstandingCents),
                    balance: $outstanding,
                    aging_days: (int) $voucher->issue_date->diffInDays(today()),
                    is_overdue: $voucher->isOverdue(),
                );
            }
        }

        $totals = new SupplierAccountStatementTotalsData(
            supplier_id: $supplier->id,
            supplier_business_name: $supplier->business_name,
            total_received: $this->centsToMoney($receivedCents),
            total_paid: $this->centsToMoney($receivedCents - $balanceCents),
            balance: $this->centsToMoney($balanceCents),
        );

        return [
            'totals' => $totals,
            'items' => collect($items),
        ];
    }

    /**
     * Vouchers that create payable debt for this supplier, in the filtered range.
     *
     * Filters by SupplierVoucherType::createsPayableBalance() rather than a hardcoded
     * [Invoice, DebitNote] list, so a future payable-creating type (or a non-payable one, like the
     * Remito HU-026 adds this same sprint) is included or excluded correctly without editing this
     * query.
     *
     * @param  array{date_from?: ?string, date_to?: ?string}  $filters
     * @return Builder<SupplierVoucher>
     */
    private function payableVouchersQuery(Supplier $supplier, array $filters): Builder
    {
        $payableTypes = array_map(
            fn (SupplierVoucherType $type): string => $type->value,
            array_filter(
                SupplierVoucherType::cases(),
                fn (SupplierVoucherType $type): bool => $type->createsPayableBalance(),
            ),
        );

        return SupplierVoucher::query()
            ->withBalanceAggregates()
            ->where('supplier_id', $supplier->id)
            ->whereIn('type', $payableTypes)
            ->where('status', '!=', SupplierVoucherStatus::Cancelled->value)
            ->when(
                ! empty($filters['date_from']),
                fn (Builder $query): Builder => $query->whereDate('issue_date', '>=', $filters['date_from']),
            )
            ->when(
                ! empty($filters['date_to']),
                fn (Builder $query): Builder => $query->whereDate('issue_date', '<=', $filters['date_to']),
            )
            ->orderBy('issue_date')
            ->orderBy('supplier_vouchers.id');
    }
}
