<?php

namespace App\Actions\Sales;

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use Illuminate\Validation\ValidationException;

class OpenSale
{
    /**
     * Open a counter sale at a point of sale (HU-039).
     *
     * The channel is always `mostrador`: `online` sales come from e-commerce orders (EPIC-15).
     * When no customer is given, the sale starts with the default Consumidor Final customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?int $userId = null): Sale
    {
        $pointOfSale = PointOfSale::findOrFail((int) $data['point_of_sale_id']);

        if (! $pointOfSale->is_active) {
            throw ValidationException::withMessages([
                'point_of_sale_id' => 'El punto de venta seleccionado no está activo.',
            ]);
        }

        $customer = empty($data['customer_id'])
            ? Customer::query()->default()->first()
            : Customer::find((int) $data['customer_id']);

        if ($customer === null || ! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => 'El cliente seleccionado no existe o no está activo.',
            ]);
        }

        return Sale::create([
            'point_of_sale_id' => $pointOfSale->id,
            'channel' => SaleChannel::Mostrador,
            'customer_id' => $customer->id,
            'user_id' => $userId ?? auth()->id(),
            'opened_at' => now(),
            'status' => SaleStatus::Open,
            'total_amount' => '0.00',
        ]);
    }
}
