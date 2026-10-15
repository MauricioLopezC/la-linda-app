<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartWebOrderPreparation
{
    /**
     * Transition a web order from 'pagado' to 'en_preparacion'.
     *
     * @throws ValidationException
     */
    public function execute(WebOrder $webOrder, User $user): WebOrder
    {
        return DB::transaction(function () use ($webOrder, $user): WebOrder {
            /** @var WebOrder $lockedOrder */
            $lockedOrder = WebOrder::query()->whereKey($webOrder->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== WebOrderStatus::Paid) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se puede iniciar la preparación de pedidos pagados.',
                ]);
            }

            $lockedOrder->update([
                'status' => WebOrderStatus::InPreparation,
                'preparation_started_at' => now(),
                'prepared_by' => $user->id,
            ]);

            $lockedOrder->refresh();

            return $lockedOrder;
        });
    }
}
