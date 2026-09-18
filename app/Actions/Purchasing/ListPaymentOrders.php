<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Models\Purchasing\PaymentOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPaymentOrders
{
    use ConvertsMoneyToCents;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     orders: LengthAwarePaginator<int, PaymentOrder>,
     *     total_egresses: string
     * }
     */
    public function handle(array $filters, int $perPage = 25): array
    {
        $query = $this->buildQuery($filters);

        // Sum of non-cancelled orders matching current filters
        $sumQuery = (clone $query)->where('status', PaymentOrderStatus::Issued->value);
        $totalCents = 0;
        foreach ($sumQuery->pluck('total_amount') as $amount) {
            $totalCents += $this->moneyToCents((string) $amount);
        }

        $orders = $query->paginate($perPage)->withQueryString();

        return [
            'orders' => $orders,
            'total_egresses' => $this->centsToMoney($totalCents),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PaymentOrder>
     */
    public function buildQuery(array $filters): Builder
    {
        $query = PaymentOrder::query()
            ->with([
                'supplier:id,business_name',
                'paymentMethods.paymentMethod:id,name',
                'items.voucher:id,type,letter,point_of_sale,number',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->where(function (Builder $subQuery) use ($lower) {
                $subQuery->whereRaw('LOWER(order_number) LIKE ?', ["%{$lower}%"])
                    ->orWhereHas('supplier', fn (Builder $sq) => $sq->whereRaw('LOWER(business_name) LIKE ?', ["%{$lower}%"]));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['payment_method_id'])) {
            $query->whereHas('paymentMethods', function (Builder $sq) use ($filters) {
                $sq->where('payment_method_id', $filters['payment_method_id']);
            });
        }

        if (! empty($filters['voucher_type'])) {
            $query->whereHas('items.voucher', function (Builder $sq) use ($filters) {
                $sq->where('type', $filters['voucher_type']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('date')->orderByDesc('id');
    }
}
