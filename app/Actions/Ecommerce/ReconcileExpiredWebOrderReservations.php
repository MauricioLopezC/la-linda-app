<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Throwable;

class ReconcileExpiredWebOrderReservations
{
    public function __construct(private readonly ReconcileWebOrderReservation $reconcileReservation) {}

    /**
     * Reconcile every pending order whose stock reservation has expired. A failure on one order is
     * reported and keeps that reservation, without stopping the reconciliation of the others.
     *
     * @return list<WebOrder> Orders whose reservation could not be verified.
     */
    public function execute(): array
    {
        $failedOrders = [];

        $expiredOrders = WebOrder::query()
            ->where('status', WebOrderStatus::Pending)
            ->where('stock_reserved_until', '<=', now())
            ->lazyById(100);

        foreach ($expiredOrders as $order) {
            try {
                $this->reconcileReservation->execute($order);
            } catch (Throwable $exception) {
                report($exception);
                $failedOrders[] = $order;
            }
        }

        return $failedOrders;
    }
}
