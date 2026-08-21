<?php

namespace App\Actions\Sales;

use App\Models\Sales\PaymentMethod;
use Illuminate\Validation\ValidationException;

class TogglePaymentMethodStatus
{
    public function handle(PaymentMethod $paymentMethod): PaymentMethod
    {
        if ($paymentMethod->is_active && $paymentMethod->isInUse()) {
            throw ValidationException::withMessages([
                'payment_method' => 'No se puede dar de baja un medio de pago que ya ha sido utilizado en operaciones registradas.',
            ]);
        }

        $paymentMethod->update([
            'is_active' => ! $paymentMethod->is_active,
        ]);

        return $paymentMethod;
    }
}
