<?php

namespace App\Data\Sales;

use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Models\Sales\CashSession;
use Spatie\LaravelData\Data;

/**
 * The open session as the closing form sees it (HU-060). The count is blind: no expected
 * amounts, totals or movements, so the cashier declares what they count instead of matching
 * a figure on screen.
 */
class CashSessionClosingData extends Data
{
    /**
     * @param  array<int, CashClosingPaymentMethodData>  $payment_methods
     */
    public function __construct(
        public int $id,
        public int $point_of_sale_number,
        public string $branch_name,
        public string $opened_at_formatted,
        public array $payment_methods,
    ) {}

    public static function fromModel(CashSession $cashSession): self
    {
        $cashSession->loadMissing('pointOfSale.warehouse.branch');

        return new self(
            id: $cashSession->id,
            point_of_sale_number: $cashSession->pointOfSale->number,
            branch_name: $cashSession->pointOfSale->warehouse->branch->name,
            opened_at_formatted: $cashSession->opened_at->format('d/m/Y H:i'),
            payment_methods: array_map(
                fn (array $total): CashClosingPaymentMethodData => CashClosingPaymentMethodData::fromExpectedTotal($total),
                app(GetCashSessionExpectedTotals::class)->handle($cashSession),
            ),
        );
    }
}
