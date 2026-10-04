<?php

namespace App\Actions\Sales;

use App\Enums\Sales\PaymentMethodKind;
use App\Models\Sales\PaymentMethod;

class CreatePaymentMethod
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => (string) $data['name'],
            'kind' => isset($data['kind']) ? PaymentMethodKind::from((string) $data['kind']) : PaymentMethodKind::Other,
            'is_enabled_online' => isset($data['is_enabled_online']) ? (bool) $data['is_enabled_online'] : false,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
