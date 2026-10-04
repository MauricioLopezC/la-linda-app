<?php

namespace App\Actions\Sales;

use App\Enums\Sales\PaymentMethodKind;
use App\Models\Sales\PaymentMethod;
use Illuminate\Validation\ValidationException;

class UpdatePaymentMethod
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $paymentMethod->is_active;

        if ($paymentMethod->is_active && ! $isActive && $paymentMethod->isInUse()) {
            throw ValidationException::withMessages([
                'payment_method' => 'No se puede dar de baja un medio de pago que ya ha sido utilizado en operaciones registradas.',
            ]);
        }

        if (isset($data['kind']) && $paymentMethod->kind->value !== (string) $data['kind'] && $paymentMethod->isInUse()) {
            throw ValidationException::withMessages([
                'kind' => 'No se puede modificar la clase de un medio de pago que ya ha sido utilizado en operaciones registradas.',
            ]);
        }

        $paymentMethod->update([
            'name' => (string) $data['name'],
            'kind' => isset($data['kind']) ? PaymentMethodKind::from((string) $data['kind']) : $paymentMethod->kind,
            'is_enabled_online' => isset($data['is_enabled_online']) ? (bool) $data['is_enabled_online'] : false,
            'is_active' => $isActive,
        ]);

        return $paymentMethod;
    }
}
