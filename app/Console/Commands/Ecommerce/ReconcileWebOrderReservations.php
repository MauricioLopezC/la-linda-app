<?php

namespace App\Console\Commands\Ecommerce;

use App\Actions\Ecommerce\ReconcileExpiredWebOrderReservations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecommerce:reconcile-reservations')]
#[Description('Verifica pagos y libera las reservas vencidas de pedidos web sin cobros en curso')]
class ReconcileWebOrderReservations extends Command
{
    public function handle(ReconcileExpiredWebOrderReservations $reconcileExpired): int
    {
        $failedOrders = $reconcileExpired->execute();

        foreach ($failedOrders as $order) {
            $this->error("No se pudo verificar la reserva del pedido {$order->formattedNumber()}.");
        }

        return $failedOrders === [] ? self::SUCCESS : self::FAILURE;
    }
}
