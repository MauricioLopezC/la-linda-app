<?php

namespace App\Actions\Ecommerce;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Atomically marks an online order as paid upon confirmed Mercado Pago payment,
 * converts it into a confirmed online sale with an issued invoice (EPIC-15),
 * and ensures strict idempotency when the same payment notification is delivered repeatedly.
 */
class MarkWebOrderAsPaid
{
    use ConvertsMoneyToCents;

    public function __construct(
        protected ConvertPaidWebOrderToSale $convertPaidWebOrderToSale,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function execute(
        WebOrder $order,
        string $paymentId,
        string $paidAmount,
        CarbonInterface $paidAt,
    ): WebOrder {
        $expectedCents = $this->moneyToCents($order->total_amount);
        $paidCents = $this->moneyToCents($paidAmount);

        if ($paidCents !== $expectedCents) {
            throw new InvalidArgumentException(
                "El importe cobrado por Mercado Pago (\${$paidAmount}) no coincide con el total del pedido N.º {$order->formattedNumber()} (\${$order->total_amount})."
            );
        }

        return DB::transaction(function () use ($order, $paymentId, $paidAmount, $paidAt): WebOrder {
            /** @var WebOrder $lockedOrder */
            $lockedOrder = WebOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedOrder->status, [WebOrderStatus::Paid, WebOrderStatus::InPreparation, WebOrderStatus::Delivered], true)) {
                if ($lockedOrder->mp_payment_id === $paymentId) {
                    if ($lockedOrder->status === WebOrderStatus::Paid) {
                        $this->convertPaidWebOrderToSale->execute($lockedOrder);
                    }

                    return $lockedOrder->loadMissing('sale.invoice');
                }

                throw new RuntimeException(
                    "El pedido N.º {$lockedOrder->formattedNumber()} ya se encuentra pagado con otro pago de Mercado Pago ({$lockedOrder->mp_payment_id})."
                );
            }

            $normalizedAmount = $this->centsToMoney($this->moneyToCents($paidAmount));

            $lockedOrder->update([
                'status' => WebOrderStatus::Paid,
                'mp_payment_id' => $paymentId,
                'paid_amount' => $normalizedAmount,
                'paid_at' => $paidAt,
            ]);

            $this->convertPaidWebOrderToSale->execute($lockedOrder);

            return $lockedOrder->loadMissing('sale.invoice');
        });
    }
}
