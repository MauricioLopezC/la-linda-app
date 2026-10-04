<?php

namespace App\Data\Sales;

use App\Enums\Sales\CashMovementType;
use App\Models\Sales\CashMovement;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Spatie\LaravelData\Data;

class SaleData extends Data
{
    /**
     * @param  array<int, SaleItemData>  $items
     * @param  array<int, SaleVatBreakdownData>  $vat_breakdown
     * @param  array<int, SalePaymentData>  $payments
     */
    public function __construct(
        public int $id,
        public ?int $cash_session_id,
        public int $point_of_sale_id,
        public int $point_of_sale_number,
        public string $branch_name,
        public string $warehouse_name,
        public string $channel,
        public string $channel_label,
        public int $customer_id,
        public string $customer_name,
        public ?string $customer_price_list_name,
        public ?int $price_list_id,
        public ?string $price_list_name,
        public string $customer_tax_condition,
        public string $customer_tax_condition_label,
        public string $customer_id_type,
        public string $customer_id_type_label,
        public ?string $customer_id_number,
        public string $invoice_type,
        public string $invoice_type_label,
        public ?string $user_name,
        public string $opened_at,
        public string $opened_at_formatted,
        public ?string $confirmed_at,
        public ?string $confirmed_at_formatted,
        public string $status,
        public string $status_label,
        public bool $is_open,
        public bool $accepts_changes,
        public string $total_amount,
        public string $net_amount,
        public string $vat_amount,
        public array $items,
        public array $vat_breakdown,
        public array $payments,
        public ?string $total_tendered,
        public ?string $change_amount,
    ) {}

    public static function fromModel(Sale $sale): self
    {
        $sale->loadMissing([
            'pointOfSale.warehouse.branch',
            'customer.priceList',
            'priceList',
            'user',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.article.unitOfMeasure',
            'items.priceList',
            'items.vatRate',
            'cashMovements.paymentMethod',
        ]);

        $invoiceType = $sale->invoiceType();

        $saleMovements = $sale->cashMovements
            ->where('type', CashMovementType::Sale)
            ->values();

        $payments = $saleMovements
            ->map(fn (CashMovement $movement): SalePaymentData => SalePaymentData::fromModel($movement))
            ->all();

        $totalTenderedCents = $saleMovements->sum(function (CashMovement $m): int {
            $amountCents = (int) round((float) $m->amount * 100);
            $tenderedCents = $m->tendered_amount !== null ? (int) round((float) $m->tendered_amount * 100) : $amountCents;

            return $tenderedCents;
        });

        $totalChangeCents = $saleMovements->sum(function (CashMovement $m): int {
            if ($m->tendered_amount === null) {
                return 0;
            }

            return max(0, (int) round(((float) $m->tendered_amount - (float) $m->amount) * 100));
        });

        $totalTendered = $saleMovements->isNotEmpty() ? number_format($totalTenderedCents / 100, 2, '.', '') : null;
        $changeAmount = $saleMovements->isNotEmpty() && $totalChangeCents > 0 ? number_format($totalChangeCents / 100, 2, '.', '') : null;

        return new self(
            id: $sale->id,
            cash_session_id: $sale->cash_session_id,
            point_of_sale_id: $sale->point_of_sale_id,
            point_of_sale_number: $sale->pointOfSale->number,
            branch_name: $sale->pointOfSale->warehouse->branch->name,
            warehouse_name: $sale->pointOfSale->warehouse->name,
            channel: $sale->channel->value,
            channel_label: $sale->channel->label(),
            customer_id: $sale->customer_id,
            customer_name: $sale->customer->name,
            customer_price_list_name: $sale->customer->priceList?->name,
            price_list_id: $sale->price_list_id,
            price_list_name: $sale->priceList?->name,
            customer_tax_condition: $sale->customer->tax_condition->value,
            customer_tax_condition_label: $sale->customer->tax_condition->label(),
            customer_id_type: $sale->customer->id_type->value,
            customer_id_type_label: $sale->customer->id_type->label(),
            customer_id_number: $sale->customer->formattedIdNumber(),
            invoice_type: $invoiceType->value,
            invoice_type_label: $invoiceType->label(),
            user_name: $sale->user?->name,
            opened_at: $sale->opened_at->toIso8601String(),
            opened_at_formatted: $sale->opened_at->format('d/m/Y H:i'),
            confirmed_at: $sale->confirmed_at?->toIso8601String(),
            confirmed_at_formatted: $sale->confirmed_at?->format('d/m/Y H:i'),
            status: $sale->status->value,
            status_label: $sale->status->label(),
            is_open: $sale->isOpen(),
            accepts_changes: $sale->acceptsChanges(),
            total_amount: $sale->total_amount,
            net_amount: $sale->netAmount(),
            vat_amount: $sale->vatAmount(),
            items: $sale->items
                ->map(fn (SaleItem $item): SaleItemData => SaleItemData::fromModel($item))
                ->values()
                ->all(),
            vat_breakdown: SaleVatBreakdownData::collect($sale->getVatBreakdown()),
            payments: $payments,
            total_tendered: $totalTendered,
            change_amount: $changeAmount,
        );
    }
}
