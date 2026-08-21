<?php

namespace App\Actions\Sales;

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
            'is_enabled_online' => isset($data['is_enabled_online']) ? (bool) $data['is_enabled_online'] : false,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
