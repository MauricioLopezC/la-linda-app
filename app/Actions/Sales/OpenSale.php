<?php

namespace App\Actions\Sales;

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\Sales\CashSession;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenSale
{
    /**
     * Open a counter sale inside the user's open cash session (HU-039).
     *
     * The point of sale and cash session are derived exclusively from the cashier's
     * open session (HU-057 contract in .ai/rules/sales.md). The sale starts with
     * the default Consumidor Final customer, channel Mostrador, status Open,
     * total 0.00 and no user-supplied parameters.
     */
    public function handle(User $user): Sale
    {
        return DB::transaction(function () use ($user): Sale {
            /** @var CashSession|null $cashSession */
            $cashSession = CashSession::query()
                ->openForUser($user->id)
                ->with('pointOfSale')
                ->lockForUpdate()
                ->first();

            if ($cashSession === null) {
                throw ValidationException::withMessages([
                    'cash_session' => 'No tenés un turno de caja abierto. Abrí la caja para empezar a vender.',
                ]);
            }

            if (! $cashSession->pointOfSale->is_active) {
                throw ValidationException::withMessages([
                    'point_of_sale' => 'El punto de venta de tu turno de caja no está activo.',
                ]);
            }

            $customer = Customer::query()->default()->first();

            if ($customer === null || ! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer' => 'El cliente Consumidor Final no está configurado o no está activo.',
                ]);
            }

            return Sale::create([
                'point_of_sale_id' => $cashSession->point_of_sale_id,
                'channel' => SaleChannel::Mostrador,
                'cash_session_id' => $cashSession->id,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'status' => SaleStatus::Open,
                'total_amount' => '0.00',
            ]);
        });
    }
}
