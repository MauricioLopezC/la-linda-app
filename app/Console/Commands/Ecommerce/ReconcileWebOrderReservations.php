<?php

namespace App\Console\Commands\Ecommerce;

use App\Actions\Ecommerce\ReconcileWebOrderReservation;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ecommerce:reconcile-reservations')]
#[Description('Verifica pagos y libera las reservas vencidas de pedidos web sin cobros en curso')]
class ReconcileWebOrderReservations extends Command
{
    public function handle(ReconcileWebOrderReservation $reconcile): int
    {
        $failed = false;

        foreach (WebOrder::query()->where('status', WebOrderStatus::Pending)
            ->where('stock_reserved_until', '<=', now())->lazyById(100) as $order) {
            try {
                $reconcile->execute($order);
            } catch (Throwable $exception) {
                report($exception);
                $this->error("No se pudo verificar la reserva del pedido {$order->formattedNumber()}.");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
