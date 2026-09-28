<?php

namespace App\Actions\Sales;

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\Sales\CashSession;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use Illuminate\Validation\ValidationException;

class OpenSale
{
    /**
     * Open a counter sale at a point of sale (HU-039), inside the user's open cash session there.
     *
     * A counter sale always belongs to a cash session (the schema enforces it), so a user without
     * an open session at that point of sale cannot sell there.
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

        $userId ??= (int) auth()->id();

        $cashSession = CashSession::query()
            ->open()
            ->where('point_of_sale_id', $pointOfSale->id)
            ->where('user_id', $userId)
            ->first();

        if ($cashSession === null) {
            throw ValidationException::withMessages([
                'point_of_sale_id' => 'No tenés un turno de caja abierto en este punto de venta. Abrí la caja para empezar a vender.',
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
            'cash_session_id' => $cashSession->id,
            'customer_id' => $customer->id,
            'user_id' => $userId,
            'opened_at' => now(),
            'status' => SaleStatus::Open,
            'total_amount' => '0.00',
        ]);
    }
}
